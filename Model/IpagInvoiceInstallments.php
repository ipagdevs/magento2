<?php

namespace Ipag\Payment\Model;

class IpagInvoiceInstallments extends \Magento\Framework\Model\AbstractModel
{
    protected function _construct()
    {
        $this->_init('Ipag\Payment\Model\ResourceModel\IpagInvoiceInstallments');
    }

    /**
     * Import Invoice
     *
     * @param $data
     * @param $order_id
     *
     * @return bool|int|mixed
     */
    public function import($data, $order_id, $ipag_id)
    {
        $saved = $this->getByField('order_id', $order_id);
        foreach($data as $d) {
            $d = $this->prepare($d, $order_id, $ipag_id);
            if (!$saved) {
                $this->add($d);
            } else {
                $this->update($d);
            }
        }
    }

    /**
     * Prepare data
     *
     * @param $data
     * @param $order_id
     *
     * @return array
     */
    public function prepare($data, $order_id, $ipag_id)
    {
        $data['order_id']           = $order_id;
        $data['ipag_invoice_id']    = $ipag_id;
        $data['installment_number'] = $data['number'];
        $data['payment_amount']     = $data['paid_amount'] ??  0;
        $data['payment_date']       = $data['payment_date'] ?? null;
        $data['ipag_tid']           = $data['payment']['transaction']['id'] ?? null;

        return $data;
    }

    /**
     * Get By Field
     *
     * @param      $field
     * @param      $value
     * @param bool $single
     *
     * @return array
     */
    public function getByField($field, $value, $single = true)
    {
        if ($single) {
            $data = $this->getCollection()->addFieldToFilter($field, $value)
                         ->getFirstItem();
        } else {
            $data = $this->getCollection()->addFieldToFilter($field, $value)
                         ->load();
        }

        return $data->toArray();
    }

    /**
     * Add Transaction
     *
     * @param $fields
     *
     * @return PayexTransaction
     */
    public function add($fields)
    {
        // Get Object Manager Instance
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        /** @var PayexTransaction $instance */
        $instance = $objectManager->create('Ipag\Payment\Model\IpagInvoiceInstallments');
        $instance->setData($fields);

        return $instance->save();
    }

    /**
     * Update Transaction
     *
     * @param $transaction_id
     * @param $fields
     *
     * @return mixed
     */
    public function update($fields)
    {
        $instance = $this->getCollection()
                        ->addFieldToFilter('order_id', $fields['order_id'])
                        ->addFieldToFilter('installment_number', $fields['installment_number'])
                        ->getFirstItem();

        $instance->addData($fields);

        return $instance->save();
    }

    /**
     * Get Transactions by Conditionals
     *
     * @param array $conditionals
     *
     * @return array
     */
    public function select(array $conditionals)
    {
        // Get Object Manager Instance
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        /** @var IpagInvoiceInstallments $instance */
        $instance = $objectManager->create('Ipag\Payment\Model\IpagInvoiceInstallments');

        $collection = $instance->getCollection()
                               ->addFieldToSelect('*');
        foreach ($conditionals as $key => $value) {
            $collection = $collection->addFieldToFilter($key, $value);
        }

        $result = $collection->load()->toArray();

        return isset($result['items']) ? $result['items'] : [];
    }
}
