<?php

namespace Maplet;

use Exception;

class Api
{
    const ERROR_MAPLET_NAME_NOT_SET         = 1000;
    const ERROR_WRONG_PARTNER_CREDENTIALS   = 1001;
    const ERROR_SETTING_WEBHOOK_FAILED      = 1002;
    const ERROR_SETTING_PARTNER_DATA_FAILED = 1003;

    const ERROR_MAP_CREATE_FAILED           = 2000;

    const ERROR_USER_ADD_FAILED             = 3000;
    const ERROR_USER_REMOVE_FAILED          = 3001;
    const ERROR_USERS_FETCH_FAILED          = 3002;

    const ERROR_PLACE_CREATE_FAILED         = 4000;
    const ERROR_PLACE_READ_FAILED           = 4001;
    const ERROR_PLACE_UPDATE_FAILED         = 4002;
    const ERROR_PLACE_DELETE_FAILED         = 4003;

    const ERROR_ROLE_CREATE_FAILED          = 5000;
    const ERROR_ROLE_DELETE_FAILED          = 5001;

    const ERROR_PLACE_FIELD_CREATE_FAILED         = 6000;
    const ERROR_PLACE_FIELD_READ_FAILED           = 6001;
    const ERROR_PLACE_FIELD_UPDATE_FAILED         = 6002;
    const ERROR_PLACE_FIELD_DELETE_FAILED         = 6003;

    private $errors = [
        self::ERROR_WRONG_PARTNER_CREDENTIALS   => 'Wrong partner credentials',
        self::ERROR_SETTING_WEBHOOK_FAILED      => 'Setting Webhook failed',
        self::ERROR_SETTING_PARTNER_DATA_FAILED => 'Setting partnerData failed',

        self::ERROR_MAP_CREATE_FAILED           => 'Map create failed',

        self::ERROR_USER_ADD_FAILED             => 'User add failed',
        self::ERROR_USER_REMOVE_FAILED          => 'User remove failed',
        self::ERROR_USERS_FETCH_FAILED          => 'Users fetch failed',

        self::ERROR_PLACE_CREATE_FAILED         => 'Place create failed',
        self::ERROR_PLACE_READ_FAILED           => 'Place read failed',
        self::ERROR_PLACE_UPDATE_FAILED         => 'Place update failed',
        self::ERROR_PLACE_DELETE_FAILED         => 'Place delete failed',

        self::ERROR_ROLE_CREATE_FAILED          => 'Role create failed',
        self::ERROR_ROLE_DELETE_FAILED          => 'Role delete failed',

        self::ERROR_PLACE_FIELD_CREATE_FAILED         => 'Place field create failed',
        self::ERROR_PLACE_FIELD_READ_FAILED           => 'Place field read failed',
        self::ERROR_PLACE_FIELD_UPDATE_FAILED         => 'Place field update failed',
        self::ERROR_PLACE_FIELD_DELETE_FAILED         => 'Place field delete failed',
    ];

    private $partnerApiUrl = "https://my.maplet.com/api/partner/v1";
    private $customerApiUrl = "https://my.maplet.com/api/customer/v1";
    private $apiKey;
    private $partnerId;
    private $mapName;
    private $client;
    private $token;
    private $headers;
    private $response;
    private $httpCode;

    /**
     * Api constructor.
     *
     * @param $partnerId
     * @param $apiKey
     * @param $mapletName
     *
     * @throws \Exception
     */
    public function __construct($partnerId, $apiKey, $mapletName)
    {
        $this->headers   = [];
        $this->response  = [];
        $this->apiKey    = $apiKey;
        $this->partnerId = $partnerId;
        $this->client    = new \GuzzleHttp\Client();
        $this->mapName   = $mapletName;
        $this->signin();
    }

    public function getClient()
    {
        return $this->client;
    }

    public function getHeaders()
    {
        return $this->headers ?: [];
    }

    /**
     * Sign in and fetch the token
     *
     * @throws \Exception
     */
    public function signin()
    {
        $res = $this->get($this->getPartnerApiUrl("signin/$this->partnerId/$this->apiKey"));
        if ($res === false) {
            $this->throwException(self::ERROR_WRONG_PARTNER_CREDENTIALS);
        }

        $this->token                 = $res->token;
        $this->headers               = [
            'headers' => [
                'Authorization' => "Bearer $this->token",
            ],
        ];
        $this->headers['exceptions'] = false;
    }

    /**
     * @param $constant
     *
     * @throws \Exception
     */
    private function throwException($constant)
    {
        $message  = empty($this->errors[$constant]) ? 'Error' : $this->errors[$constant];
        $response = $this->getResponse();
        if (!empty($response->error)) {
            $message .= ' - ' . $response->error;
        }

        throw new Exception($message, $constant);
    }


    /**
     * For doorToDoor maps one should pass statusCodes as options associative array
     * Example:
     * $statusData = [
     *  'status1' => [
     *      'name' => 'Abc',
     *      'color' => '#ff0000'
     *  ]
     * ];
     *
     * @param      $type
     * @param      $name
     * @param null $options
     *
     * @return mixed
     * @throws \Exception
     */
    public function createMap($type, $name, $options = null)
    {
        $res = $this->post($this->getPartnerApiUrl('map'), [
            'type'    => $type,
            'name'    => $name,
            'options' => $options,
        ]);

        if ($res === false) {
            $this->throwException(self::ERROR_MAP_CREATE_FAILED);
        }

        $this->mapName = $res->mapName;

        return $res;
    }

    /**
     * Webhook URL (You can use {action}, {type}, and {id} as variables in the URL)
     * Action = "create" / "update" / "delete"
     * Type = "place" / "item"
     * Id = place / item unique ID
     *
     * @param $url
     * @param $username
     * @param $password
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    public function setWebhookUrl($url, $username = "", $password = "")
    {
        if(strlen($username) > 0) {
          $res = $this->put($this->getPartnerMapUrl('webhook'), [
              'url'                  => $url,
              'basicAuthCredentials' => [
                  'userName' => $username,
                  'password' => $password,
              ],
          ]);
        }
        else {
          $res = $this->put($this->getPartnerMapUrl('webhook'), [
              'url'                  => $url
          ]);
        }

        if ($res === false) {
            $this->throwException(self::ERROR_SETTING_WEBHOOK_FAILED);
        }

        return $res;
    }

    /**
     * @param $data
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    public function setPartnerData($data)
    {
        $res = $this->put($this->getPartnerMapUrl('partnerData'), $data);

        if ($res === false) {
            $this->throwException(self::ERROR_SETTING_PARTNER_DATA_FAILED);
        }

        return $res;
    }

    /**
     * @return array|bool|mixed
     * @throws \Exception
     */
    public function getMapUsers()
    {
        $res = $this->get($this->getCustomerMapUrl('maplet/users'));

        if ($res === false) {
            $this->throwException(self::ERROR_USERS_FETCH_FAILED);
        }

        return $res;
    }

    /**
     * @param $userId
     *
     * @return array|bool|mixed
     * @throws \Exception
     */
    public function getMapUser($userId)
    {
        $res = $this->get($this->getCustomerMapUrl('maplet/users/' . $userId));

        if ($res === false) {
            $this->throwException(self::ERROR_USERS_FETCH_FAILED);
        }

        return $res;
    }

    /**
     * @param        $phoneNumber
     *
     * @param string $roleName
     *
     * @return mixed
     * @throws \Exception
     */
    public function addMapUser($phoneNumber, $roleName = '')
    {
        $res = $this->post($this->getCustomerMapUrl('users/invite'), [
            'phoneNumber' => $phoneNumber,
            'roleName'    => $roleName,
        ]);

        if ($res === false) {
            $this->throwException(self::ERROR_USER_ADD_FAILED);
        }

        return $res;
    }

    /**
     * @param $phoneNumberOrdConnetionId
     *
     * @return mixed
     * @throws \Exception
     */
    public function removeMapUser($phoneNumberOrdConnetionId)
    {
        $res = $this->delete($this->getCustomerMapUrl('users/invite/' . $phoneNumberOrdConnetionId));
        if ($res === false) {
            $this->throwException(self::ERROR_USER_REMOVE_FAILED);
        }

        return $res;
    }

    /**
     * @param $placeDataArray
     *
     * @return mixed
     * @throws \Exception
     */
    public function createPlace($placeDataArray)
    {
        $res = $this->post($this->getCustomerMapUrl('places'), $placeDataArray);
        if ($res === false) {
            $this->throwException(self::ERROR_PLACE_CREATE_FAILED);
        }

        return $res;
    }

    /**
     * @param $placeId
     *
     * @return mixed
     * @throws \Exception
     */
    public function readPlace($placeId)
    {
        $res = $this->get($this->getCustomerMapUrl('places/' . $placeId));
        if ($res === false) {
            $this->throwException(self::ERROR_PLACE_READ_FAILED);
        }

        return $res;
    }

    /**
     * @param $placeId
     * @param $placeDataArray
     *
     * @return mixed
     * @throws \Exception
     */
    public function updatePlace($placeId, $placeDataArray)
    {
        $placeDataArray['_id'] = $placeId;
        $res                   = $this->put($this->getCustomerMapUrl('places/' . $placeId), $placeDataArray);
        if ($res === false) {
            $this->throwException(self::ERROR_PLACE_UPDATE_FAILED);
        }

        return $res;
    }

    /**
     * @param $placeId
     *
     * @return mixed
     * @throws \Exception
     */
    public function deletePlace($placeId)
    {
        $res = $this->delete($this->getCustomerMapUrl('places/' . $placeId));
        if ($res === false) {
            $this->throwException(self::ERROR_PLACE_DELETE_FAILED);
        }

        return $res;
    }

    /**
     * @param $roleName
     * @param $templateName
     *
     * @return mixed
     * @throws \Exception
     */
    public function createRole($roleName, $templateName)
    {
        $res = $this->post($this->getCustomerMapUrl('roles'), [
            'roleName'     => $roleName,
            'templateName' => $templateName,
        ]);
        if ($res === false) {
            $this->throwException(self::ERROR_ROLE_CREATE_FAILED);
        }

        return $res;
    }

    /**
     * @param $roleName
     *
     * @return mixed
     * @throws \Exception
     */
    public function deleteRole($roleName)
    {
        $res = $this->delete($this->getCustomerMapUrl('roles/' . $roleName));
        if ($res === false) {
            $this->throwException(self::ERROR_ROLE_DELETE_FAILED);
        }

        return $res;
    }

    private function request($url, $method, $formParams = [])
    {
        $res            = $this->client->request(
            $method,
            $url,
            array_merge(
                $this->headers,
                [
                    'form_params' => $formParams,
                ]
            )
        );
        $this->response = json_decode($res->getBody());
        $this->httpCode = $res->getStatusCode();
        if ($this->httpCode != 200) {
            return false;
        }

        return $this->response;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getHttpCode()
    {
        return $this->httpCode;
    }

    private function post($url, $params = [])
    {
        return $this->request($url, 'POST', $params);
    }

    private function get($url, $params = [])
    {
        return $this->request($url, 'GET', $params);
    }

    private function put($url, $params = [])
    {
        return $this->request($url, 'PUT', $params);
    }

    private function delete($url, $params = [])
    {
        return $this->request($url, 'DELETE', $params);
    }

    public function getPartnerApiUrl($append = null)
    {
        return $this->partnerApiUrl . ($append ? '/' . $append : '');
    }

    /**
     * @param string $partnerApiUrl
     *
     * @return Api
     */
    public function setPartnerApiUrl($partnerApiUrl)
    {
        $this->partnerApiUrl = $partnerApiUrl;

        return $this;
    }

    public function getCustomerApiUrl($append = null)
    {
        return $this->customerApiUrl . ($append ? '/' . $append : '');
    }

    /**
     * @param string $customerApiUrl
     *
     * @return Api
     */
    public function setCustomerApiUrl($customerApiUrl)
    {
        $this->customerApiUrl = $customerApiUrl;

        return $this;
    }

    public function getCustomerMapUrl($append = null)
    {
        return $this->getCustomerApiUrl($this->mapName . ($append ? '/' . $append : ''));
    }

    /**
     * @param null $append
     *
     * @return string
     * @throws \Exception
     */
    public function getPartnerMapUrl($append = null)
    {
        if (!$this->mapName) {
            $this->throwException(self::ERROR_MAPLET_NAME_NOT_SET);
        }

        return $this->getPartnerApiUrl($this->mapName . ($append ? '/' . $append : ''));
    }

    /**
     * @param mixed $apiKey
     *
     * @return Api
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    /**
     * @param mixed $partnerId
     *
     * @return Api
     */
    public function setPartnerId($partnerId)
    {
        $this->partnerId = $partnerId;

        return $this;
    }

    /**
     * @param string $mapName
     *
     * @return Api
     */
    public function setMapName($mapName)
    {
        $this->mapName = $mapName;

        return $this;
    }

    // Place field API

    /**
     * @param $fieldName
     * @param $fieldType
     * @param $fieldOptions
     *
     * @return mixed
     * @throws \Exception
     */
    public function createPlaceField($fieldName, $fieldType, $fieldOptions = [])
    {
        $field = [];
        $field['fieldOptions'] = $fieldOptions;
        $field['name'] = $fieldName;
        $field['type'] = $fieldType;
        $res = $this->post($this->getCustomerMapUrl('places/fields'), $field);
        if ($res === false) {
            $this->throwException(self::ERROR_PLACE_FIELD_CREATE_FAILED);
        }

        return $res;
    }

    /**
     * @param $fieldName
     *
     * @return mixed
     * @throws \Exception
     */
    public function readPlaceField($fieldName)
    {
        $res = $this->get($this->getCustomerMapUrl('places/fields/' . $fieldName));
        if ($res === false) {
            $this->throwException(self::ERROR_PLACE_FIELD_READ_FAILED);
        }

        return $res;
    }

    /**
     * @param $fieldName
     * @param $fieldOptions
     *
     * @return mixed
     * @throws \Exception
     */
    public function updatePlaceField($fieldName, $fieldOptions)
    {
        $res                   = $this->put($this->getCustomerMapUrl('places/fields/' . $fieldName), $fieldOptions);
        if ($res === false) {
            $this->throwException(self::ERROR_PLACE_FIELD_UPDATE_FAILED);
        }

        return $res;
    }

    /**
     * @param $fieldName
     *
     * @return mixed
     * @throws \Exception
     */
    public function deletePlaceField($fieldName)
    {
        $res = $this->delete($this->getCustomerMapUrl('places/fields/' . $fieldName));
        if ($res === false) {
            $this->throwException(self::ERROR_PLACE_FIELD_DELETE_FAILED);
        }

        return $res;
    }
}
