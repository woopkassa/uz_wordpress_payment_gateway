<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2012-2021 Wooppay
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @copyright   Copyright (c) 2012-2021 Wooppay
 * @author      Artyom Narmagambetov <anarmagambetov@wooppay.com>
 * @version     2.0
 */
class WooppayRestClient
{
	/**
	 * @var string
	 */
	private $hostUrl;

	/**
	 * @var resource
	 */
	private $connection;

	/**
	 * @var string - user token
	 */
	private $authToken;

	/**
	 * @param string $url
	 */
	public function __construct($url)
	{
		$this->hostUrl = $url;
	}

	/**
	 * @param string $url
	 */
	private function createConnection($url)
	{
		$this->connection = curl_init($url);
	}

	/**
	 * @return string[]
	 */
	private function getDefaultHeaders()
	{
		return array("Content-Type:application/json", "ip-address:{$_SERVER['REMOTE_ADDR']}");
	}

	/**
	 * @param string $body - json encoded string with request params
	 * @param array $headerList
	 */
	private function setRequestOptions($body, $headerList)
	{
		curl_setopt($this->connection, CURLOPT_HEADER, 1);
		curl_setopt($this->connection, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->connection, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($this->connection, CURLOPT_TIMEOUT, 120);
		curl_setopt($this->connection, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($this->connection, CURLOPT_SSL_VERIFYHOST, 0);


		curl_setopt($this->connection, CURLOPT_HTTPHEADER, $headerList);
		curl_setopt($this->connection, CURLOPT_POSTFIELDS, $body);
	}

	/**
	 * @return bool|string
	 */
	private function sendRequest()
	{
		return curl_exec($this->connection);
	}

	/**
	 * @param $rawResponse
	 * @return array|null
	 */
	private function getResponse($rawResponse)
	{
		$headerSize = curl_getinfo($this->connection, CURLINFO_HEADER_SIZE);
		$result = substr($rawResponse, $headerSize);
		return json_decode($result);
	}

	/**
	 * Checks response status. If 4xx or 5xx then throws exception.
	 * @throws Exception
	 */
	private function checkResponse()
	{
		$responseStatus = curl_getinfo($this->connection, CURLINFO_HTTP_CODE);
		if ($responseStatus >= 400) {
			throw new Exception("Ошибка: $responseStatus");
		}
	}

	/**
	 * Close CURL session
	 */
	private function closeConnection()
	{
		curl_close($this->connection);
	}

	/**
	 * Returns response after POST request into API
	 *
	 * @param string $coreApiMethod - URL of called method
	 * @param string $body - json encoded attributes of request
	 * @param $headerList
	 * @return array | null
	 * @throws Exception
	 */
	private function handlePostRequest($coreApiMethod, $body, $headerList)
	{
		try {
			$this->createConnection($coreApiMethod);
			$this->setRequestOptions($body, $headerList);
			$rawResponse = $this->sendRequest();
			$response = $this->getResponse($rawResponse);
			$this->checkResponse();
			$this->closeConnection();
			return $response;
		} catch (Exception $e) {
			throw new Exception($e->getMessage());
		}
	}


	/**
	 * @param string $login
	 * @param string $pass
	 * @return boolean
	 * @throws Exception
	 */
	public function login($login, $pass)
	{
		$coreApiMethod = $this->hostUrl . '/auth';
		$body = json_encode(array('login' => $login, 'password' => $pass));
		$response = $this->handlePostRequest($coreApiMethod, $body, $this->getDefaultHeaders());
		try {
			$this->authToken = $response->token;
			return true;
		} catch (Exception $exception) {
			return false;
		}
	}


	/**
	 * @param string $referenceId
	 * @param string $backUrl
	 * @param string $requestUrl
	 * @param float $amount
	 * @param string $serviceName
	 * @param string $addInfo
	 * @param string $deathDate
	 * @param string $description
	 * @param string $userEmail
	 * @param string $userPhone
	 * @param int $option
	 * @return array
	 * @throws Exception
	 */
	public function createInvoice(
		$referenceId,
		$backUrl,
		$requestUrl,
		$amount,
		$serviceName = '',
		$addInfo = '',
		$deathDate = '',
		$description = '',
		$userEmail = '',
		$userPhone = '',
		$option = 0
	)
	{
		$coreApiMethod = $this->hostUrl . '/invoice/create';
		$attributes = array(
			'reference_id' => $referenceId,
			'back_url' => $backUrl,
			'request_url' => $requestUrl,
			'amount' => (float)$amount,
			'option' => $option,
			'death_date' => $deathDate,
			'description' => $description,
			'user_email' => $userEmail,
			'user_phone' => $userPhone,
			'add_info' => $addInfo,
		);
		if ($serviceName) {
			$attributes['serviceName'] = $serviceName;
		}
		$body = json_encode($attributes);
		$headers = $this->getDefaultHeaders();
		$headers[] = "Authorization:$this->authToken";

		return $this->handlePostRequest($coreApiMethod, $body, $headers);
	}


}
