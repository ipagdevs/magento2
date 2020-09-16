<?php

namespace Ipag\Payment\Setup;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * Upgrade DB schema for a module
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;

        $installer->startSetup();

        if (version_compare($context->getVersion(), '1.0.5', '<')) {
            if (!$installer->getConnection()->isTableExists($installer->getTable('ipag_invoice_installments'))) {
                // Install Ipag Installments Table
                $table = $installer->getConnection()
                    ->newTable($installer->getTable('ipag_invoice_installments'))
                    ->addColumn(
                        'id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        255,
                        ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                        'Table Key ID'
                    )
                    ->addColumn(
                        'order_id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        50,
                        ['nullable' => true],
                        'Increment Order Id'
                    )
                    ->addColumn(
                        'ipag_invoice_id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        50,
                        ['nullable' => true],
                        'Transaction ID'
                    )
                    ->addColumn(
                        'installment_number',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        50,
                        ['nullable' => true],
                        'Installment number'
                    )
                    ->addColumn(
                        'due_date',
                        \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
                        null,
                        ['nullable' => true],
                        'Due date'
                    )
                    ->addColumn(
                        'amount',
                        \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                        '17,2',
                        ['nullable' => true],
                        'Amount'
                    )
                    ->addColumn(
                        'paid_amount',
                        \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                        '17,2',
                        ['nullable' => true],
                        'Paid Amount'
                    )
                    ->addColumn(
                        'payment_date',
                        \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
                        null,
                        ['nullable' => true],
                        'Payment date'
                    )
                    ->addColumn(
                        'ipag_tid',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        250,
                        ['nullable' => true],
                        'iPag TID'
                    )
                    ->addIndex(
                        $installer->getIdxName('ipag_invoice_installments', ['order_id', 'id']),
                        ['order_id', 'id']
                    )
                    ->setComment('iPag Invoice Installment');
                $installer->getConnection()->createTable($table);
            }
        }

        $installer->endSetup();
    }
}
