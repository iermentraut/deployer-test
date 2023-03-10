<?php

declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Tests\Functional\Bundle\BenchmarkBundle\Providers;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Constraint\IsType;
use Shopware\Bundle\BenchmarkBundle\Provider\OrdersProvider;

class OrdersProviderTest extends ProviderTestCase
{
    protected const SERVICE_ID = OrdersProvider::class;
    protected const EXPECTED_KEYS_COUNT = 1;
    protected const EXPECTED_TYPES = [
        'list' => IsType::TYPE_ARRAY,
    ];

    public function testGetArrayKeysFit(): void
    {
        $dbalConnection = $this->getContainer()->get(Connection::class);
        $basicContent = $this->openDemoDataFile('basic_setup');
        $dbalConnection->executeStatement($basicContent);

        parent::testGetArrayKeysFit();
    }

    public function testGetValidateTypes(): void
    {
        $dbalConnection = $this->getContainer()->get(Connection::class);
        $basicContent = $this->openDemoDataFile('basic_setup');
        $dbalConnection->executeStatement($basicContent);

        parent::testGetValidateTypes();
    }

    /**
     * @group BenchmarkBundle
     */
    public function testGetOrdersList(): void
    {
        $this->resetConfig();
        $this->installDemoData('orders_detailed');

        $resultData = $this->getBenchmarkData();
        static::assertArrayHasKey('orderId', $resultData['list'][0]);
        static::assertArrayHasKey('currency', $resultData['list'][0]);
        static::assertArrayHasKey('shippingCosts', $resultData['list'][0]);
        static::assertArrayHasKey('customer', $resultData['list'][0]);
        static::assertArrayHasKey('analytics', $resultData['list'][0]);
        static::assertArrayHasKey('shipment', $resultData['list'][0]);
        static::assertArrayHasKey('payment', $resultData['list'][0]);
        static::assertArrayHasKey('items', $resultData['list'][0]);
        static::assertSame(1, $resultData['list'][0]['orderId']);
        static::assertSame('EUR', $resultData['list'][0]['currency']);
        static::assertSame(15.0, (float) $resultData['list'][0]['shippingCosts']);
        static::assertSame([
            'registered' => true,
            'birthYear' => 1993,
            'birthMonth' => 1,
            'gender' => 'male',
            'registerDate' => '2011-11-23',
            'hasNewsletter' => false,
            'billing' => [
                'country' => 'DE',
            ],
            'shipping' => [
                'country' => 'GR',
            ],
        ], $resultData['list'][0]['customer']);
        static::assertSame([
            'device' => 'mobile',
            'referer' => false,
        ], $resultData['list'][0]['analytics']);
        static::assertSame([
            'name' => 'others',
            'cost' => [
                'minPrice' => 14.00,
                'maxPrice' => 14.00,
            ],
        ], $resultData['list'][0]['shipment']);
        static::assertSame([
            'name' => 'others',
            'cost' => [
                'percentCosts' => 0.0,
                'absoluteCosts' => 0.0,
                'absoluteCostsPerCountry' => 0.0,
            ],
        ], $resultData['list'][0]['payment']);

        $currentDetailId = 0;
        foreach ($resultData['list'][0]['items'] as &$orderItem) {
            static::assertGreaterThan($currentDetailId, $orderItem['detailId']);
            $currentDetailId = $orderItem['detailId'];
            unset($orderItem['detailId']);
        }
        unset($orderItem);

        static::assertSame([
            [
                'unitPrice' => 150.00,
                'totalPrice' => 150.00,
                'amount' => 1,
                'packUnit' => '',
                'purchaseUnit' => '',
            ], [
                'unitPrice' => 20.00,
                'totalPrice' => 80.00,
                'amount' => 4,
                'packUnit' => '',
                'purchaseUnit' => '',
            ],
        ], $resultData['list'][0]['items']);
    }

    /**
     * @group BenchmarkBundle
     */
    public function testGetOrdersListBatch(): void
    {
        $this->installDemoData('orders_detailed');

        $this->getContainer()->get('dbal_connection')->executeStatement('UPDATE `s_benchmark_config` SET last_order_id=0, batch_size=1;');
        $singleResult = $this->getBenchmarkData();

        static::assertCount(1, $singleResult['list']);

        $this->getContainer()->get('dbal_connection')->executeStatement('UPDATE `s_benchmark_config` SET last_order_id=0, batch_size=5;');
        $multipleResults = $this->getBenchmarkData();

        static::assertCount(5, $multipleResults['list']);
    }

    /**
     * @group BenchmarkBundle
     */
    public function testGetOrdersListDateConsidered(): void
    {
        $this->installDemoData('orders_detailed');

        $this->getContainer()->get('dbal_connection')->executeStatement('UPDATE `s_benchmark_config` SET last_order_id=4, batch_size=1;');
        $resultData = $this->getBenchmarkData();

        static::assertSame(5, $resultData['list'][0]['orderId']);
    }

    /**
     * @group BenchmarkBundle
     */
    public function testGetOrdersListMultipleExecutionsFetchesNewOrders(): void
    {
        $this->installDemoData('orders_detailed');

        $this->getContainer()->get('dbal_connection')->executeStatement('UPDATE `s_benchmark_config` SET last_order_id=4, batch_size=1;');
        $firstResult = $this->getBenchmarkData();

        $this->sendStatistics();

        $secondResult = $this->getBenchmarkData();

        static::assertSame(5, $firstResult['list'][0]['orderId']);
        static::assertSame(6, $secondResult['list'][0]['orderId']);

        $this->getContainer()->get('dbal_connection')->executeStatement('UPDATE `s_benchmark_config` SET last_order_id=4, batch_size=2;');
        $thirdResultSet = $this->getBenchmarkData();

        $this->sendStatistics();

        $forthResultSet = $this->getBenchmarkData();

        static::assertSame(5, $thirdResultSet['list'][0]['orderId']);
        static::assertSame(6, $thirdResultSet['list'][1]['orderId']);

        static::assertSame(7, $forthResultSet['list'][0]['orderId']);
        // Only seven results possible, no eighth result available
        static::assertEmpty($forthResultSet['list'][1]);
    }

    /**
     * @group BenchmarkBundle
     */
    public function testGetOrdersListIdConfigGetsUpdated(): void
    {
        $this->installDemoData('orders_detailed');

        $this->getContainer()->get('dbal_connection')->executeStatement('UPDATE `s_benchmark_config` SET last_order_id=4, batch_size=1 WHERE shop_id=1;');
        $this->sendStatistics();

        static::assertSame(5, (int) $this->getContainer()->get('dbal_connection')->fetchOne('SELECT last_order_id FROM s_benchmark_config WHERE shop_id=1'));
    }

    /**
     * @group BenchmarkBundle
     */
    public function testGetOrdersListPerShop(): void
    {
        $this->installDemoData('orders_detailed');
        $this->installDemoData('second_config');
        $provider = $this->getProvider();

        $this->getContainer()->get('dbal_connection')->executeStatement('UPDATE `s_benchmark_config` SET last_order_id=0, batch_size=10;');

        $resultData = $provider->getBenchmarkData($this->getShopContextByShopId(1));
        static::assertCount(7, $resultData['list']);

        $resultData = $provider->getBenchmarkData($this->getShopContextByShopId(2));
        static::assertCount(2, $resultData['list']);
    }
}
