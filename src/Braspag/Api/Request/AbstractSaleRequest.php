<?php
namespace BraspagNonOfficial\Api\Request;

use BraspagNonOfficial\Merchant;
use BraspagNonOfficial\Api\Sale;

abstract class AbstractSaleRequest
{

    private $merchant;

    public function __construct(Merchant $merchant)
    {
        $this->merchant = $merchant;
    }

    public abstract function execute($param);

    protected abstract function unserialize($json);

    protected function sendRequest($method, $url, Sale $sale = null)
    {
        $headers = [
            'Accept: application/json',
            'Accept-Encoding: gzip',
            'User-Agent: Braspag/v2 PHP SDK',
            'MerchantId: ' . $this->merchant->getId(),
            'MerchantKey: ' . $this->merchant->getKey(),
            'RequestId: ' . uniqid()
        ];

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);

        switch ($method) {
            case 'GET':
                break;
            case 'POST':
                curl_setopt($curl, CURLOPT_POST, true);
                break;
            default:
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        }

        if ($sale !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($sale));
            $headers[] = 'Content-Type: application/json';
        } else {
            $headers[] = 'Content-Length: 0';
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if (curl_errno($curl)) {
            throw new \RuntimeException('Curl error: ' . curl_error($curl));
        }

        curl_close($curl);

        return $this->readResponse($statusCode, $response);
    }

    protected function readResponse($statusCode, $responseBody)
    {
        $unserialized = null;

        switch ($statusCode) {
            case 200:
            case 201:
                $unserialized = $this->unserialize($responseBody);
                break;
            case 400:
                $exception = null;
                $response = json_decode($responseBody);

                if (is_object($response)) {
                    $code = $this->getResponseCode($response);
                    throw new BraspagError($response->Message, $code);
                }

                foreach ($response as $error) {
                    $code = $this->getResponseCode($error);
                    throw new BraspagError($error->Message, $code);
                }
            case 404:
                throw new BraspagRequestException('Resource not found', 404, null);
            default:
                throw new BraspagRequestException('Unknown status', $statusCode);
        }

        return $unserialized;
    }

    protected function getResponseCode($element)
    {
        $code = -1;
        if (property_exists($element, "Code")) {
            $code = $element->Code;
        }
        return $code;
    }
}