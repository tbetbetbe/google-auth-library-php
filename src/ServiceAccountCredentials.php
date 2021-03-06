<?php
/*
 * Copyright 2015 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Auth;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

/**
 * ServiceAccountCredentials supports authorization using a Google service
 * account.
 *
 * (cf https://developers.google.com/accounts/docs/OAuth2ServiceAccount)
 *
 * It's initialized using the json key file that's downloadable from developer
 * console, which should contain a private_key and client_email fields that it
 * uses.
 *
 * Use it with AuthTokenFetcher to authorize http requests:
 *
 *   use GuzzleHttp\Client;
 *   use Google\Auth\ServiceAccountCredentials;
 *   use Google\Auth\AuthTokenFetcher;
 *
 *   $stream = Stream::factory(get_file_contents(<my_key_file>));
 *   $sa = new ServiceAccountCredentials(
 *       'https://www.googleapis.com/auth/taskqueue',
 *        $stream);
 *   $client = new Client([
 *      'base_url' => 'https://www.googleapis.com/taskqueue/v1beta2/projects/',
 *      'defaults' => ['auth' => 'google_auth']  // authorize all requests
 *   ]);
 *   $client->getEmitter()->attach(new AuthTokenFetcher($sa));
 *
 *   $res = $client->('myproject/taskqueues/myqueue');
 */
class ServiceAccountCredentials extends CredentialsLoader
{
  /**
   * Create a new ServiceAccountCredentials.
   *
   * @param string|array scope the scope of the access request, expressed
   *   either as an Array or as a space-delimited String.
   *
   * @param array jsonKey JSON credentials.
   *
   * @param string jsonKeyPath the path to a file containing JSON credentials.  If
   *   jsonKeyStream is set, it is ignored.
   *
   * @param string sub an email address account to impersonate, in situations when
   *   the service account has been delegated domain wide access.
   */
  public function __construct($scope, $jsonKey,
                              $jsonKeyPath = null, $sub = null)
  {
    if (is_null($jsonKey)) {
      $jsonKeyStream = Stream::factory(file_get_contents($jsonKeyPath));
      $jsonKey = json_decode($jsonKeyStream->getContents(), true);
    }
    if (!array_key_exists('client_email', $jsonKey)) {
      throw new \InvalidArgumentException(
          'json key is missing the client_email field');
    }
    if (!array_key_exists('private_key', $jsonKey)) {
      throw new \InvalidArgumentException(
          'json key is missing the private_key field');
    }
    $this->auth = new OAuth2([
        'audience' => self::TOKEN_CREDENTIAL_URI,
        'issuer' => $jsonKey['client_email'],
        'scope' => $scope,
        'signingAlgorithm' => 'RS256',
        'signingKey' => $jsonKey['private_key'],
        'sub' => $sub,
        'tokenCredentialUri' => self::TOKEN_CREDENTIAL_URI
    ]);
  }

 /**
  * Implements FetchAuthTokenInterface#getCacheKey.
  */
  public function getCacheKey()
  {
    $key = $this->auth->getIssuer() . ':' . $this->auth->getCacheKey();
    if ($sub = $this->auth->getSub()) {
      $key .= ':' . $sub;
    }
    return $key;
  }
}
