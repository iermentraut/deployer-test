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

namespace Shopware\Tests\Functional\Controllers\Frontend;

use Doctrine\DBAL\Connection;
use Enlight_Components_Test_Controller_TestCase as ControllerTestCase;
use Shopware\Tests\Functional\Traits\ContainerTrait;
use Shopware\Tests\Functional\Traits\DatabaseTransactionBehaviour;

class AccountTest extends ControllerTestCase
{
    use ContainerTrait;
    use DatabaseTransactionBehaviour;

    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        parent::setUp();
    }

    public function tearDown(): void
    {
        $this->Template()->clearAllAssign();
        parent::tearDown();
    }

    public function testPasswordWillBeChanged(): void
    {
        $hash = $this->getNextResetHash('test@example.com');
        $before = $this->getPasswordForEmail('test@example.com');

        $this->Request()
            ->setMethod('POST')
            ->setPost(
                [
                    'hash' => $hash,
                    'password' => [
                        'password' => 'shopware1',
                        'passwordConfirmation' => 'shopware1',
                    ],
                ]
            );
        $this->dispatch('account/resetPassword');

        $changed = $this->getPasswordForEmail('test@example.com');

        static::assertNotSame($before, $changed);
    }

    public function testPasswordWillNotChangeOnErrorDifferentPasswords(): void
    {
        $hash = $this->getNextResetHash('test@example.com');
        $before = $this->getPasswordForEmail('test@example.com');

        $this->Request()
            ->setMethod('POST')
            ->setPost(
                [
                    'hash' => $hash,
                    'password' => [
                        'password' => 'shopware1',
                        'passwordConfirmation' => 'shopware12',
                    ],
                ]
            );

        $this->dispatch('account/resetPassword');

        $after = $this->getPasswordForEmail('test@example.com');

        static::assertFalse($this->Response()->isRedirect());
        static::assertSame($before, $after);
    }

    public function testPasswordWillNotChangeOnErrorToShort(): void
    {
        $hash = $this->getNextResetHash('test@example.com');
        $before = $this->getPasswordForEmail('test@example.com');

        $this->Request()
            ->setMethod('POST')
            ->setPost(
                [
                    'hash' => $hash,
                    'password' => [
                        'password' => 'test',
                        'passwordConfirmation' => 'test',
                    ],
                ]
            );
        $this->dispatch('account/resetPassword');

        $after = $this->getPasswordForEmail('test@example.com');

        static::assertSame($before, $after);
    }

    public function testPasswordWillBeChangedOnInvalidCustomers(): void
    {
        $hash = $this->getNextResetHash('test@example.com');
        $before = $this->getPasswordForEmail('test@example.com');

        // Make user invalid
        $this->connection->executeStatement('UPDATE `s_user` SET `salutation` = null WHERE `email` = :email', ['email' => 'test@example.com']);

        $this->Request()
            ->setMethod('POST')
            ->setPost(
                [
                    'hash' => $hash,
                    'password' => [
                        'password' => 'shopware12',
                        'passwordConfirmation' => 'shopware12',
                    ],
                ]
            );
        $this->dispatch('account/resetPassword');

        $changed = $this->getPasswordForEmail('test@example.com');
        static::assertSame($before, $changed);
    }

    public function testNoServerErrorOnMissingHashParameter(): void
    {
        $this->Request()->setMethod('GET');
        $response = $this->dispatch('account/resetPassword');

        static::assertNotNull($response);
    }

    /**
     * Asserts, that no password change takes place, when an old hash is used.
     */
    public function testExistingHashIsInvalidated(): void
    {
        $hash = $this->getNextResetHash('test@example.com');
        $secondHash = $this->getNextResetHash('test@example.com');
        $before = $this->getPasswordForEmail('test@example.com');

        $this->Request()
            ->setMethod('POST')
            ->setPost(
                [
                    'hash' => $hash,
                    'password' => [
                        'password' => 'fd12179f-d454-42e4-ae90-9271f806088d',
                        'passwordConfirmation' => 'fd12179f-d454-42e4-ae90-9271f806088d',
                    ],
                ]
            );
        $this->dispatch('account/resetPassword');

        $changed = $this->getPasswordForEmail('test@example.com');

        static::assertSame($before, $changed);
    }

    private function getNextResetHash(string $mail): string
    {
        // Request a variant that is not the default one
        $this->Request()
            ->setMethod('POST')
            ->setPost('email', $mail);

        $this->dispatch('account/password');
        $this->reset();

        return $this->connection->fetchOne(
            'SELECT `hash` FROM `s_core_optin` WHERE `type` IN (:types) ORDER BY `datum` DESC LIMIT 1',
            [':types' => ['password', 'swPassword']],
            [':types' => Connection::PARAM_STR_ARRAY]
        );
    }

    private function getPasswordForEmail(string $email): ?string
    {
        return $this->connection->fetchOne('SELECT `password` FROM `s_user` WHERE `email` = :email', ['email' => $email]) ?: null;
    }
}
