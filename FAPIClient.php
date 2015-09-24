<?php

class FAPIClient_Pest {

	public $curl_opts = array(CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 10);
	public $base_url;
	public $last_response;
	public $last_request;
	public $last_headers;
	public $throw_exceptions = true;

	public function __construct($base_url) {
		if (!function_exists('curl_init')) {
			throw new FAPIClient_Exception('CURL module not available! Pest requires CURL. See http://php.net/manual/en/book.curl.php');
		} if (ini_get('open_basedir') == '' && strtolower(ini_get('safe_mode')) == 'off') {
			$this->curl_opts['CURLOPT_FOLLOWLOCATION'] = true;
		} $this->base_url = $base_url;
		$this->curl_opts[CURLOPT_HEADERFUNCTION] = array($this, 'handle_header');
	}

	public function setupAuth($user, $pass, $auth = 'basic') {
		$this->curl_opts[CURLOPT_HTTPAUTH] = constant('CURLAUTH_' . strtoupper($auth));
		$this->curl_opts[CURLOPT_USERPWD] = $user . ":" . $pass;
	}

	public function setupProxy($host, $port, $user = NULL, $pass = NULL) {
		$this->curl_opts[CURLOPT_PROXYTYPE] = 'HTTP';
		$this->curl_opts[CURLOPT_PROXY] = $host;
		$this->curl_opts[CURLOPT_PROXYPORT] = $port;
		if ($user && $pass) {
			$this->curl_opts[CURLOPT_PROXYUSERPWD] = $user . ":" . $pass;
		}
	}

	public function get($url) {
		$curl = $this->prepRequest($this->curl_opts, $url);
		$body = $this->doRequest($curl);
		$body = $this->processBody($body);
		return $body;
	}

	public function post($url, $data, $headers = array()) {
		$data = (is_array($data)) ? http_build_query($data) : $data;
		$curl_opts = $this->curl_opts;
		$curl_opts[CURLOPT_CUSTOMREQUEST] = 'POST';
		$headers[] = 'Content-Length: ' . strlen($data);
		$curl_opts[CURLOPT_HTTPHEADER] = $headers;
		$curl_opts[CURLOPT_POSTFIELDS] = $data;
		$curl = $this->prepRequest($curl_opts, $url);
		$body = $this->doRequest($curl);
		$body = $this->processBody($body);
		return $body;
	}

	public function put($url, $data, $headers = array()) {
		$data = (is_array($data)) ? http_build_query($data) : $data;
		$curl_opts = $this->curl_opts;
		$curl_opts[CURLOPT_CUSTOMREQUEST] = 'PUT';
		$headers[] = 'Content-Length: ' . strlen($data);
		$curl_opts[CURLOPT_HTTPHEADER] = $headers;
		$curl_opts[CURLOPT_POSTFIELDS] = $data;
		$curl = $this->prepRequest($curl_opts, $url);
		$body = $this->doRequest($curl);
		$body = $this->processBody($body);
		return $body;
	}

	public function patch($url, $data, $headers = array()) {
		$data = (is_array($data)) ? http_build_query($data) : $data;
		$curl_opts = $this->curl_opts;
		$curl_opts[CURLOPT_CUSTOMREQUEST] = 'PATCH';
		$headers[] = 'Content-Length: ' . strlen($data);
		$curl_opts[CURLOPT_HTTPHEADER] = $headers;
		$curl_opts[CURLOPT_POSTFIELDS] = $data;
		$curl = $this->prepRequest($curl_opts, $url);
		$body = $this->doRequest($curl);
		$body = $this->processBody($body);
		return $body;
	}

	public function delete($url) {
		$curl_opts = $this->curl_opts;
		$curl_opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
		$curl = $this->prepRequest($curl_opts, $url);
		$body = $this->doRequest($curl);
		$body = $this->processBody($body);
		return $body;
	}

	public function lastBody() {
		return $this->last_response['body'];
	}

	public function lastStatus() {
		return $this->last_response['meta']['http_code'];
	}

	public function lastHeader($header) {
		if (empty($this->last_headers[strtolower($header)])) {
			return NULL;
		} return $this->last_headers[strtolower($header)];
	}

	protected function processBody($body) {
		return $body;
	}

	protected function processError($body) {
		return $body;
	}

	protected function prepRequest($opts, $url) {
		if (strncmp($url, $this->base_url, strlen($this->base_url)) != 0) {
			$url = $this->base_url . $url;
		} $curl = curl_init($url);
		foreach ($opts as $opt => $val)
			curl_setopt($curl, $opt, $val); $this->last_request = array('url' => $url);
		if (isset($opts[CURLOPT_CUSTOMREQUEST]))
			$this->last_request['method'] = $opts[CURLOPT_CUSTOMREQUEST]; else
			$this->last_request['method'] = 'GET'; if (isset($opts[CURLOPT_POSTFIELDS]))
			$this->last_request['data'] = $opts[CURLOPT_POSTFIELDS]; return $curl;
	}

	private function handle_header($ch, $str) {
		if (preg_match('/([^:]+):\s(.+)/m', $str, $match)) {
			$this->last_headers[strtolower($match[1])] = trim($match[2]);
		} return strlen($str);
	}

	private function doRequest($curl) {
		$this->last_headers = array();
		$body = curl_exec($curl);
		$meta = curl_getinfo($curl);
		$this->last_response = array('body' => $body, 'meta' => $meta);
		curl_close($curl);
		$this->checkLastResponseForError();
		return $body;
	}

	protected function checkLastResponseForError() {
		if (!$this->throw_exceptions)
			return; $meta = $this->last_response['meta'];
		$body = $this->last_response['body'];
		if (!$meta)
			return; $err = null;
		switch ($meta['http_code']) {
			case 400: throw new FAPIClient_Pest_BadRequest($this->processError($body));
				break;
			case 401: throw new FAPIClient_Pest_Unauthorized($this->processError($body));
				break;
			case 403: throw new FAPIClient_Pest_Forbidden($this->processError($body));
				break;
			case 404: throw new FAPIClient_Pest_NotFound($this->processError($body));
				break;
			case 405: throw new FAPIClient_Pest_MethodNotAllowed($this->processError($body));
				break;
			case 409: throw new FAPIClient_Pest_Conflict($this->processError($body));
				break;
			case 410: throw new FAPIClient_Pest_Gone($this->processError($body));
				break;
			case 422: throw new FAPIClient_Pest_InvalidRecord($this->processError($body));
				break;
			default: if ($meta['http_code'] >= 400 && $meta['http_code'] <= 499)
					throw new FAPIClient_Pest_ClientError($this->processError($body)); elseif ($meta['http_code'] >= 500 && $meta['http_code'] <= 599)
					throw new FAPIClient_Pest_ServerError($this->processError($body)); elseif (!$meta['http_code'] || $meta['http_code'] >= 600) {
					throw new FAPIClient_Pest_UnknownResponse($this->processError($body));
				}
		}
	}

}

class FAPIClient_Pest_Exception extends Exception {

}

class FAPIClient_Pest_UnknownResponse extends FAPIClient_Pest_Exception {

}

class FAPIClient_Pest_ClientError extends FAPIClient_Pest_Exception {

}

class FAPIClient_Pest_BadRequest extends FAPIClient_Pest_ClientError {

}

class FAPIClient_Pest_Unauthorized extends FAPIClient_Pest_ClientError {

}

class FAPIClient_Pest_Forbidden extends FAPIClient_Pest_ClientError {

}

class FAPIClient_Pest_NotFound extends FAPIClient_Pest_ClientError {

}

class FAPIClient_Pest_MethodNotAllowed extends FAPIClient_Pest_ClientError {

}

class FAPIClient_Pest_Conflict extends FAPIClient_Pest_ClientError {

}

class FAPIClient_Pest_Gone extends FAPIClient_Pest_ClientError {

}

class FAPIClient_Pest_InvalidRecord extends FAPIClient_Pest_ClientError {

}

class FAPIClient_Pest_ServerError extends FAPIClient_Pest_Exception {

}

class FAPIClient_PestJSON extends FAPIClient_Pest {

	public function post($url, $data, $headers = array()) {
		return parent::post($url, json_encode($data), $headers);
	}

	public function put($url, $data, $headers = array()) {
		return parent::put($url, json_encode($data), $headers);
	}

	protected function prepRequest($opts, $url) {
		$opts[CURLOPT_HTTPHEADER][] = 'Accept: application/json';
		$opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
		return parent::prepRequest($opts, $url);
	}

	public function processBody($body) {
		return json_decode($body, true);
	}

}

class FAPIClient {

	public $client;
	public $invoice;
	public $item;
	public $periodicInvoice;
	public $currency;
	public $paymentType;
	public $country;
	public $settings;
	public $email;
	public $log;
	public $user;
	public $payment;
	public $validator;
	private $code;
	public $RESTClient;

	public function __construct($username, $password, $url = 'http://fapi.cz') {
		$this->RESTClient = new FAPIClient_PestJSON($url);
		$this->RESTClient->setupAuth($username, $password);
		$resources = array('client', 'invoice', 'item', 'periodicInvoice', 'currency', 'paymentType', 'country', 'settings', 'email', 'log', 'user');
		foreach ($resources as $resource) {
			$class = 'FAPIClient_' . ucfirst($resource) . 'Resource';
			$this->$resource = new $class($this->RESTClient, $this);
		} $this->client = new FAPIClient_ClientResource($this->RESTClient, $this);
		$this->invoice = new FAPIClient_InvoiceResource($this->RESTClient, $this);
		$this->item = new FAPIClient_ItemResource($this->RESTClient, $this);
		$this->periodicInvoice = new FAPIClient_PeriodicInvoiceResource($this->RESTClient, $this);
		$this->currency = new FAPIClient_CurrencyResource($this->RESTClient, $this);
		$this->paymentType = new FAPIClient_PaymentTypeResource($this->RESTClient, $this);
		$this->country = new FAPIClient_CountryResource($this->RESTClient, $this);
		$this->settings = new FAPIClient_SettingsResource($this->RESTClient, $this);
		$this->email = new FAPIClient_EmailResource($this->RESTClient, $this);
		$this->log = new FAPIClient_LogResource($this->RESTClient, $this);
		$this->user = new FAPIClient_UserResource($this->RESTClient, $this);
		$this->payment = new FAPIClient_PaymentResource($this->RESTClient, $this);
		$this->validator = new FAPIClient_ValidatorResource($this->RESTClient, $this);
	}

	public function checkConnection() {
		try {
			$this->RESTClient->get('');
			$this->setCode($this->RESTClient->last_response['meta']['http_code']);
			if ($this->RESTClient->last_response['body'] !== '{}') {
				throw new RuntimeException('Cannot establish a connection to the server.');
			}
		} catch (FAPIClient_Pest_Exception $exception) {
			$this->processException($exception);
		}
	}

	public function processException(FAPIClient_Pest_Exception $exception) {
		$json = json_decode($exception->getMessage());
		$class = str_replace('Pest_', '', get_class($exception)) . 'Exception';
		$exception = new $class(isset($json->message) ? $json->message : null);
		throw $exception;
	}

	public function getCode() {
		return $this->code;
	}

	public function setCode($code) {
		$this->code = $code;
	}

}

class FAPIClient_ClientResource extends FAPIClient_Resource {

	protected $url = '/clients';

}

class FAPIClient_CountryResource extends FAPIClient_Resource {

	protected $url = '/countries';

	public function get($id) {
		throw new FAPIClient_InvalidActionException;
	}

	public function create($data) {
		throw new FAPIClient_InvalidActionException;
	}

	public function update($id, $data) {
		throw new FAPIClient_InvalidActionException;
	}

	public function delete($id) {
		throw new FAPIClient_InvalidActionException;
	}

	public function search($conditions) {
		throw new FAPIClient_InvalidActionException;
	}

}

class FAPIClient_CurrencyResource extends FAPIClient_Resource {

	protected $url = '/currencies';

	public function get($id) {
		throw new FAPIClient_InvalidActionException;
	}

	public function create($data) {
		throw new FAPIClient_InvalidActionException;
	}

	public function update($id, $data) {
		throw new FAPIClient_InvalidActionException;
	}

	public function delete($id) {
		throw new FAPIClient_InvalidActionException;
	}

}

class FAPIClient_EmailResource extends FAPIClient_Resource {

	protected $url = '/emails';

	public function synchronize($emails) {
		try {
			$response = $this->client->put($this->url . '/synchronize', array('emails' => $emails));
			$this->setCode();
			return $response['emails'];
		} catch (FAPIClient_Pest_Exception $exception) {
			$this->parent->processException($exception);
		}
	}

	public function get($id) {
		throw new FAPIClient_InvalidActionException;
	}

	public function update($id, $emails) {
		throw new FAPIClient_InvalidActionException;
	}

	public function delete($id) {
		throw new FAPIClient_InvalidActionException;
	}

}

class FAPIClient_Exception extends Exception {

}

class FAPIClient_InvalidActionException extends FAPIClient_Exception {

}

class FAPIClient_UnknownResponseException extends FAPIClient_Exception {

}

class FAPIClient_ClientErrorException extends FAPIClient_Exception {

}

class FAPIClient_BadRequestException extends FAPIClient_ClientErrorException {

}

class FAPIClient_UnauthorizedException extends FAPIClient_ClientErrorException {

}

class FAPIClient_ForbiddenException extends FAPIClient_ClientErrorException {

}

class FAPIClient_NotFoundException extends FAPIClient_ClientErrorException {

}

class FAPIClient_MethodNotAllowedException extends FAPIClient_ClientErrorException {

}

class FAPIClient_ConflictException extends FAPIClient_ClientErrorException {

}

class FAPIClient_GoneException extends FAPIClient_ClientErrorException {

}

class FAPIClient_InvalidRecordException extends FAPIClient_ClientErrorException {

}

class FAPIClient_ServerErrorException extends FAPIClient_Exception {

}

class FAPIClient_InvoiceResource extends FAPIClient_Resource {

	protected $url = '/invoices';

	/**
	 * @param integer
	 * @param integer
	 * @param string
	 * @param string
	 * @param integer
	 * @param string
	 * @param string
	 * @param string
	 * @param string
	 * @param string
	 * @param string
	 * @return array
	 */
	public function getAll($limit = null, $offset = null, $order = null, $searchKeyword = null, $user = null, $type = null, $status = null, $createDate = null, $dateFrom = null, $dateTo = null, $lastModifiedAfter = null)
	{
		try {
			$url = $this->url;

			if (isset($limit) || isset($offset) || isset($order) || isset($searchKeyword) || isset($user) || isset($type) || isset($status) || isset($createDate) || isset($dateFrom) || isset($dateTo) || isset($lastModifiedAfter)) {
				$parameters = array(
					'limit' => $limit,
					'offset' => $offset,
					'order' => $order,
					'search' => $searchKeyword,
					'user' => $user,
					'type' => $type,
					'status' => $status,
					'create_date' => $createDate,
					'date_from' => $dateFrom,
					'date_to' => $dateTo,
					'last_modified_after' => $lastModifiedAfter,
				);

				$url .= sprintf('?%s', http_build_query($parameters));
			}

			$response = $this->client->get($url);
			$this->setCode();

			return $response['invoices'];
		} catch (FAPIClient_Pest_Exception $exception) {
			$this->parent->processException($exception);
		}
	}

}

class FAPIClient_ItemResource extends FAPIClient_Resource {

	protected $url = '/items';

	public function getAll() {
		throw new FAPIClient_InvalidActionException;
	}

	public function search($conditions) {
		throw new FAPIClient_InvalidActionException;
	}

}

class FAPIClient_LogResource extends FAPIClient_Resource {

	protected $url = '/logs';

	public function get($id) {
		throw new FAPIClient_InvalidActionException;
	}

	public function create($data) {
		throw new FAPIClient_InvalidActionException;
	}

	public function update($id, $data) {
		throw new FAPIClient_InvalidActionException;
	}

	public function delete($id) {
		throw new FAPIClient_InvalidActionException;
	}

	public function search($conditions) {
		throw new FAPIClient_InvalidActionException;
	}

}

class FAPIClient_PaymentTypeResource extends FAPIClient_Resource {

	protected $url = '/payment-types';

	public function get($id) {
		throw new FAPIClient_InvalidActionException;
	}

	public function create($data) {
		throw new FAPIClient_InvalidActionException;
	}

	public function update($id, $data) {
		throw new FAPIClient_InvalidActionException;
	}

	public function delete($id) {
		throw new FAPIClient_InvalidActionException;
	}

	public function search($conditions) {
		throw new FAPIClient_InvalidActionException;
	}

}

class FAPIClient_PeriodicInvoiceResource extends FAPIClient_Resource {

	protected $url = '/periodic-invoices';



	/**
	 * @param integer
	 * @param integer
	 * @param string
	 * @param string
	 * @param string
	 * @param bool
	 * @return array
	 */
	public function getAll($limit = null, $offset = null, $order = null, $search = null, $status = null, $detailed = null)
	{
		try {
			$url = $this->url;

			if (isset($limit) || isset($offset) || isset($order) || isset($search) || isset($status) || isset($detailed)) {
				$parameters = array(
					'limit' => $limit,
					'offset' => $offset,
					'order' => $order,
					'search' => $search,
					'status' => $status,
					'detailed' => $detailed,
				);

				$url .= '?' . http_build_query($parameters);
			}

			$response = $this->client->get($url);
			$this->setCode();

			return $response['periodic_invoices'];

		} catch (FAPIClient_Pest_Exception $exception) {
			$this->parent->processException($exception);
		}
	}

	/**
	 * @param string
	 * @param string
	 * @return array
	 */
	public function count($search = null, $status = null)
	{
		try {
			$url = $this->url . '/count';

			if (isset($search) || isset($status)) {
				$parameters = array(
					'search' => $search,
					'status' => $status,
				);

				$url .= '?' . http_build_query($parameters);
			}

			$response = $this->client->get($url);
			$this->setCode();

			return $response['count'];

		} catch (FAPIClient_Pest_Exception $exception) {
			$this->parent->processException($exception);
		}
	}

}

abstract class FAPIClient_Resource {

	protected $client;
	protected $parent;

	public function __construct(FAPIClient_PestJSON $client, FAPIClient$parent) {
		$this->client = $client;
		$this->parent = $parent;
	}

	public function getAll() {
		try {
			$response = $this->client->get($this->url);
			$this->setCode();
			return $response[str_replace(array('-', '/'), array('_', ''), $this->url)];
		} catch (FAPIClient_Pest_Exception $exception) {
			$this->parent->processException($exception);
		}
	}

	public function get($id) {
		try {
			$response = $this->client->get($this->url . '/' . $id);
			$this->setCode();
			return $response;
		} catch (FAPIClient_Pest_Exception $exception) {
			$this->parent->processException($exception);
		}
	}

	public function create($data) {
		try {
			$response = $this->client->post($this->url, $data);
			$this->setCode();
			return $response;
		} catch (FAPIClient_Pest_Exception $exception) {
			$this->parent->processException($exception);
		}
	}

	public function update($id, $data) {
		try {
			$response = $this->client->put($this->url . '/' . $id, $data);
			$this->setCode();
			return $response;
		} catch (FAPIClient_Pest_Exception $exception) {
			$this->parent->processException($exception);
		}
	}

	public function delete($id) {
		try {
			$this->client->delete($this->url . '/' . $id);
			$this->setCode();
			return null;
		} catch (FAPIClient_Pest_Exception $exception) {
			$this->parent->processException($exception);
		}
	}

	public function search($conditions) {
		try {
			$response = $this->client->get($this->url . '/search?' . http_build_query($conditions));
			$this->setCode();
			return $response;
		} catch (FAPIClient_Pest_Exception $exception) {
			$this->parent->processException($exception);
		}
	}

	protected function setCode() {
		$this->parent->setCode($this->client->last_response['meta']['http_code']);
	}

}

class FAPIClient_SettingsResource extends FAPIClient_Resource {

	protected $url = '/settings';

	public function getAll($user = null)
	{
		try {
			$url = $this->url;

			if (isset($user)) {
				$url .= '?' . http_build_query(array(
					'user' => $user,
				));
			}

			$response = $this->client->get($url);
			$this->setCode();

			return $response['settings'];
		} catch (FAPIClient_Pest_Exception $exception) {
			$this->parent->processException($exception);
		}
	}

	public function get($key) {
		try {
			$response = $this->client->get($this->url . '/' . $key);
			$this->setCode();
			return $response['value'];
		} catch (FAPIClient_Pest_Exception $exception) {
			$this->parent->processException($exception);
		}
	}

	public function create($data) {
		try {
			$response = $this->client->post($this->url, $data);
			$this->setCode();
			return $response['value'];
		} catch (FAPIClient_Pest_Exception $exception) {
			$this->parent->processException($exception);
		}
	}

	public function update($key, $data) {
		try {
			$response = $this->client->put($this->url . '/' . $key, $data);
			$this->setCode();
			return $response['value'];
		} catch (FAPIClient_Pest_Exception $exception) {
			$this->parent->processException($exception);
		}
	}

	public function delete($key) {
		try {
			$this->client->delete($this->url . '/' . $key);
			$this->setCode();
			return null;
		} catch (FAPIClient_Pest_Exception $exception) {
			$this->parent->processException($exception);
		}
	}

}

class FAPIClient_UserResource extends FAPIClient_Resource {

	protected $url = '/users';

	public function getProfile() {
		try {
			$response = $this->client->get('/user');
			$this->setCode();
			return $response;
		} catch (FAPIClient_Pest_Exception $exception) {
			$this->parent->processException($exception);
		}
	}

}

class FAPIClient_PaymentResource extends FAPIClient_Resource {

	protected $url = '/payments';



	/**
	 * @param integer
	 * @param integer
	 * @param string
	 * @param string
	 * @param string
	 * @param bool
	 * @param bool
	 * @param bool
	 * @return array
	 */
	public function getAll($limit = null, $offset = null, $order = null, $date = null, $search = null, $unpaired = null, $hidden = null, $detailed = null)
	{
		try {
			$url = $this->url;

			if (isset($limit) || isset($offset) || isset($order) || isset($date) || isset($search) || isset($unpaired) || isset($hidden) || isset($detailed)) {
				$parameters = array(
					'limit' => $limit,
					'offset' => $offset,
					'order' => $order,
					'date' => $date,
					'search' => $search,
					'unpaired' => $unpaired,
					'hidden' => $hidden,
					'detailed' => $detailed,
				);

				$url .= sprintf('?%s', http_build_query($parameters));
			}

			$response = $this->client->get($url);
			$this->setCode();

			return $response['payments'];
		} catch (FAPIClient_Pest_Exception $exception) {
			$this->parent->processException($exception);
		}
	}

	/**
	 * @param string
	 * @param string
	 * @param bool
	 * @return integer
	 */
	public function count($date = null, $search = null, $unpaired = null)
	{
		try {
			$url = sprintf('%s/count', $this->url);

			if (isset($date) || isset($search) || isset($unpaired)) {
				$parameters = array(
					'date' => $date,
					'search' => $search,
					'unpaired' => $unpaired,
				);

				$url .= sprintf('?%s', http_build_query($parameters));
			}

			$response = $this->client->get($url);
			$this->setCode();
			return $response['count'];
		} catch (FAPIClient_Pest_Exception $exception) {
			$this->parent->processException($exception);
		}
	}

	/**
	 * @param integer
	 * @throws FAPIClient_InvalidActionException
	 */
	public function delete($id)
	{
		throw new FAPIClient_InvalidActionException;
	}

}

class FAPIClient_ValidatorResource extends FAPIClient_Resource {

	protected $url = '/validators';

	/**
	 * @param string
	 * @param mixed
	 */
	public function validate($type, $value)
	{
		try {
			$url = sprintf('%s/%s', $this->url, $type);

			$data = array('value' => $value);

			$response = $this->client->post($url, $data);

			$this->setCode();
			return $response;
		} catch (FAPIClient_Pest_Exception $exception) {
			$this->parent->processException($exception);
		}
	}

}
