<?php

namespace Ipag\Payment\Model\Support;

abstract class PaymentResponseMapper
{
    public static function translateToV1($response)
    {
        $id = ArrUtils::get($response, 'id');
        $tid = ArrUtils::get($response, 'attributes.tid');
        $authId = ArrUtils::get($response, 'attributes.authorization_id');
        $nsu = ArrUtils::get($response, 'attributes.nsu');
        $amount = ArrUtils::get($response, 'attributes.amount');
        $installments = ArrUtils::get($response, 'attributes.installments');
        $acquirer = ArrUtils::get($response, 'attributes.acquirer.name');
        $acquirerMessage = ArrUtils::get($response, 'attributes.acquirer.message');
        $urlAuthentication = ArrUtils::get($response, 'attributes.url_authentication');
        $digitableLine = ArrUtils::get($response, 'attributes.boleto.digitable_line');
        $urlCallback = ArrUtils::get($response, 'attributes.callback_url');
        $urlRedirect = ArrUtils::get($response, 'attributes.redirect_url');
        $createAt = ArrUtils::get($response, 'attributes.created_at');

        //@NOTE: This fields are captured by another way in v2 responses.
        // $error =
        // $errorMessage =

        $payment = [
            'status' => ArrUtils::get($response, 'attributes.status.code'),
            'message' => ArrUtils::get($response, 'attributes.status.message'),
        ];

        $order = [
            'orderId' => ArrUtils::get($response, 'attributes.order_id')
        ];

        $customer = [
            'name' => ArrUtils::get($response, 'attributes.customer.name'),
            'email' => ArrUtils::get($response, 'attributes.customer.email'),
            'phone' => ArrUtils::get($response, 'attributes.customer.phone'),
            'cpfCnpj' => ArrUtils::get($response, 'attributes.customer.cpf_cnpj'),
        ];

        $checkAddressExists = ArrUtils::exists($response, 'attributes.customer.address.street');

        $customerAddress = !$checkAddressExists ? null : [
            'street' => ArrUtils::get($response, 'attributes.customer.address.street'),
            'number' => ArrUtils::get($response, 'attributes.customer.address.number'),
            'complement' => ArrUtils::get($response, 'attributes.customer.address.complement'),
            'district' => ArrUtils::get($response, 'attributes.customer.address.district'),
            'city' => ArrUtils::get($response, 'attributes.customer.address.city'),
            'state' => ArrUtils::get($response, 'attributes.customer.address.state'),
            'zipCode' => ArrUtils::get($response, 'attributes.customer.address.zip_code'),
        ];

        $checkShippingAddressExists = ArrUtils::exists($response, 'attributes.customer.shipping_address.street');

        $customerShippingAddress = !$checkShippingAddressExists ? null : [
            'street' => ArrUtils::get($response, 'attributes.customer.shipping_address.street'),
            'number' => ArrUtils::get($response, 'attributes.customer.shipping_address.number'),
            'complement' => ArrUtils::get($response, 'attributes.customer.shipping_address.complement'),
            'district' => ArrUtils::get($response, 'attributes.customer.shipping_address.district'),
            'city' => ArrUtils::get($response, 'attributes.customer.shipping_address.city'),
            'state' => ArrUtils::get($response, 'attributes.customer.shipping_address.state'),
            'zipCode' => ArrUtils::get($response, 'attributes.customer.shipping_address.zip_code'),
        ];

        $customerAddress = $checkShippingAddressExists ? $customerShippingAddress : $customerAddress;

        if ($customerAddress) {
            $customer['address'] = $customerAddress;
        }

        $checkAntifraudExists = ArrUtils::exists($response, 'attributes.antifraud.score');

        $antifraud = !$checkAntifraudExists ? null : [
            'score' => ArrUtils::get($response, 'attributes.antifraud.score'),
            'status' => ArrUtils::get($response, 'attributes.antifraud.status'),
            'message' => ArrUtils::get($response, 'attributes.antifraud.message'),
        ];

        $checkPixExists = ArrUtils::exists($response, 'attributes.pix.qrcode');

        $pix = !$checkPixExists ? null : [
            'link' => ArrUtils::get($response, 'attributes.pix.link'),
            'qrCode' => ArrUtils::get($response, 'attributes.pix.qrcode'),
        ];

        $splitRules = self::prepareSplitRulesFromResponse($response);

        $history = self::prepareHistoryFromResponse($response);

        $mappedResponse = compact(
            'id',
            'tid',
            'authId',
            'nsu',
            'amount',
            'acquirer',
            'installments',
            'acquirerMessage',
            'urlAuthentication',
            'digitableLine',
            'urlCallback',
            'urlRedirect',
            'createAt',
            'payment',
            'order',
            'customer',
            'antifraud',
            'pix',
            'splitRules',
            'history'
        );

        return $mappedResponse;
    }

    private static function prepareSplitRulesFromResponse($response)
    {
        $splitRules = [];

        $splitRulesData = ArrUtils::get($response, 'attributes.split_rules', []);

        if (is_array($splitRulesData)) {
            foreach ($splitRulesData as $splitRuleData) {
                $splitRule = [
                    'rule'=> ArrUtils::get($splitRuleData, 'id'),
                    'seller_id'=> ArrUtils::get($splitRuleData, 'attributes.receiver_id'),
                    'amount'=> ArrUtils::get($splitRuleData, 'attributes.amount'),
                    'percentage'=> ArrUtils::get($splitRuleData, 'attributes.percentage'),
                    'liable'=> ArrUtils::get($splitRuleData, 'attributes.liable'),
                    'charge_processing_fee'=> ArrUtils::get($splitRuleData, 'attributes.charge_processing_fee'),
                    'hold_receivables'=> ArrUtils::get($splitRuleData, 'attributes.hold_receivables'),
                ];

                $splitRules[] = $splitRule;
            }
        }

        return $splitRules;
    }

    private static function prepareHistoryFromResponse($response)
    {
        $history = [];

        $historyData = ArrUtils::get($response, 'attributes.history', []);

        if (is_array($historyData)) {
            foreach ($historyData as $historyItemData) {
                $historyItem = [
                    'amount'=> ArrUtils::get($historyItemData, 'amount'),
                    'operationType'=> ArrUtils::get($historyItemData, 'type'),
                    'status'=> ArrUtils::get($historyItemData, 'status'),
                    'responseCode'=> ArrUtils::get($historyItemData, 'response_code'),
                    'responseMessage'=> ArrUtils::get($historyItemData, 'response_message'),
                    'authorizationCode'=> ArrUtils::get($historyItemData, 'authorization_code'),
                    'authorizationId'=> ArrUtils::get($historyItemData, 'authorization_id'),
                    'authorizationNsu'=> ArrUtils::get($historyItemData, 'authorization_nsu'),
                    'createdAt'=> ArrUtils::get($historyItemData, 'created_at'),
                ];

                $history[] = $historyItem;
            }
        }

        return $history;
    }
}