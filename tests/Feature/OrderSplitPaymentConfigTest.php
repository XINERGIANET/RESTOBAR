<?php

namespace Tests\Feature;

use Tests\TestCase;

class OrderSplitPaymentConfigTest extends TestCase
{
    public function test_orders_config_declares_split_account_toggle(): void
    {
        $this->assertArrayHasKey('split_account_enabled', config('orders'));
        $this->assertIsBool(config('orders.split_account_enabled'));
        $this->assertArrayHasKey('split_account_branch_ids', config('orders'));
        $this->assertIsArray(config('orders.split_account_branch_ids'));
    }
}
