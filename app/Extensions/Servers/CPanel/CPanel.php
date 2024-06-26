<?php

namespace App\Extensions\Servers\CPanel;

use App\Classes\Extensions\Server;
use App\Helpers\ExtensionHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CPanel extends Server
{
    /**
     * Get the extension metadata
     * 
     * @return array
     */
    public function getMetadata()
    {
        return [
            'display_name' => 'CPanel',
            'version' => '1.0.1',
            'author' => 'Paymenter',
            'website' => 'https://paymenter.org',
        ];
    }

    private function request($method, $endpoint, $data = [])
    {
        $host = rtrim(ExtensionHelper::getConfig('CPanel', 'host'), '/');
        $response = Http::withHeaders([
            'Authorization' => 'whm ' . ExtensionHelper::getConfig('CPanel', 'username') . ':' . ExtensionHelper::getConfig('CPanel', 'apikey'),
        ])->$method($host . '/json-api' . $endpoint, $data);
        if ($response->failed()) {
            dd($response->body(), $response->status());
            throw new \Exception('Error while requesting API');
        }
        return $response;
    }

    /**
     * Get all the configuration for the extension
     * 
     * @return array
     */
    public function getConfig()
    {
        return [
            [
                'name' => 'host',
                'type' => 'text',
                'friendlyName' => 'Hostname',
                'validation' => 'url:http,https',
                'required' => true,
            ],
            [
                'name' => 'hostaccess',
                'type' => 'text',
                'friendlyName' => 'Access for users hostname',
                'validation' => 'url:http,https',
                'required' => false,
            ],
            [
                'name' => 'username',
                'type' => 'text',
                'friendlyName' => 'Username',
                'required' => true,
            ],
            [
                'name' => 'apikey',
                'type' => 'text',
                'friendlyName' => 'API key',
                'required' => true,
            ],
        ];
    }

    /**
     * Get product config
     * 
     * @param array $options
     * @return array
     */
    public function getProductConfig($options)
    {
        // Get all the packages
        $response = $this->request('get', '/listpkgs');
        $packages = $response->json();
        $packageOptions = [];
        foreach ($packages['package'] as $package) {
            $packageOptions[] = [
                'value' => $package['name'],
                'name' => $package['name'],
            ];
        }

        return [
            [
                'name' => 'package',
                'type' => 'dropdown',
                'friendlyName' => 'Package',
                'options' => $packageOptions,
                'required' => true,
            ],
        ];
    }

    /**
     * Get configurable options for users
     *
     * @param array $options
     * @return array
     */
    public function getUserConfig()
    {
        return [
            [
                'name' => 'domain',
                'type' => 'text',
                'validation' => 'domain',
                'friendlyName' => 'Domain',
                'required' => true,
            ],
            [
                'name' => 'password',
                'type' => 'text',
                'validation' => 'max:6|regex:/^[a-zA-Z0-9]+$/i',
                'friendlyName' => 'Password (max 6 characters and only letters and numbers)', 
                'required' => false, //if left empty we will create a password at creation
            ]
        ];
    }

    /**
     * Create a server
     * 
     * @param User $user
     * @param array $params
     * @param Order $order
     * @param OrderProduct $orderProduct
     * @param array $configurableOptions
     * @return bool
     */
    public function createServer($user, $params, $order, $orderProduct, $configurableOptions)
    {
        $username = Str::random();
        if(empty($params['config']['password'])){
            $password = Str::random();
        } else {
            $password = $params['config']['password'];
        }

        // If first one is a number, add a letter
        if (is_numeric($username[0])) {
            $username = 'a' . substr($username, 1);
        }
        $this->request(
            'get',
            '/createacct',
            [
                'api.version' => 1,
                'username' => $username,
                'password' => $password,
                'contactemail' => $user->email,
                'domain' => $params['config']['domain'],
                'plan' => $params['package']
            ]
        );

        ExtensionHelper::setOrderProductConfig('username', strtolower($username), $orderProduct->id);
        ExtensionHelper::setOrderProductConfig('password', strtolower($password), $orderProduct->id);


        return true;
    }

    /**
     * Suspend a server
     * 
     * @param User $user
     * @param array $params
     * @param Order $order
     * @param OrderProduct $orderProduct
     * @param array $configurableOptions
     * @return bool
     */
    public function suspendServer($user, $params, $order, $orderProduct, $configurableOptions)
    {
        $this->request(
            'get',
            '/suspendacct',
            [
                'api.version' => 1,
                'user' => $params['config']['username'],
            ]
        );

        return true;
    }

    /**
     * Unsuspend a server
     * 
     * @param User $user
     * @param array $params
     * @param Order $order
     * @param OrderProduct $orderProduct
     * @param array $configurableOptions
     * @return bool
     */
    public function unsuspendServer($user, $params, $order, $orderProduct, $configurableOptions)
    {
        $this->request(
            'get',
            '/unsuspendacct',
            [
                'api.version' => 1,
                'user' => $params['config']['username'],
            ]
        );

        return true;
    }

    /**
     * Terminate a server
     * 
     * @param User $user
     * @param array $params
     * @param Order $order
     * @param OrderProduct $orderProduct
     * @param array $configurableOptions
     * @return bool
     */
    public function terminateServer($user, $params, $order, $orderProduct, $configurableOptions)
    {
        $this->request(
            'get',
            '/removeacct',
            [
                'api.version' => 1,
                'user' => $params['config']['username'],
            ]
        );

        return true;
    }
/**
 * Get link for user Cpanel
 * 
 * @param User $user
 * @param array $params
 */
    public function getLink($user, $params){
        return ExtensionHelper::getConfig('cpanel', 'hostaccess');
    }
}
