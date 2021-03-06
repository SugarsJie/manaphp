<?php

defined('UNIT_TESTS_ROOT') || require __DIR__ . '/bootstrap.php';

class StoreAdapterFileTest extends TestCase
{
    protected $_di;

    public function setUp()
    {
        parent::setUp();

        $this->_di = new ManaPHP\Di\FactoryDefault();
    }

    public function test_exists()
    {
        $store = new \ManaPHP\Store\Adapter\File('/d/store/test');

        $store->delete('var');

        $this->assertFalse($store->exists('var'));
        $store->set('var', 'value');
        $this->assertTrue($store->exists('var'));
    }

    public function test_get()
    {
        $store = new \ManaPHP\Store\Adapter\File('/d/store/test');

        $store->delete('var');

        $this->assertFalse($store->get('var'));
        $store->set('var', 'value');
        $this->assertSame('value', $store->get('var'));
    }

    public function test_set()
    {
        $store = new \ManaPHP\Store\Adapter\File('/d/store/test');

        $store->set('var', '');
        $this->assertSame('', $store->get('var'));

        $store->set('var', 'value');
        $this->assertSame('value', $store->get('var'));

        $store->set('var', '{}');
        $this->assertSame('{}', $store->get('var'));
    }

    public function test_delete()
    {
        $store = new \ManaPHP\Store\Adapter\File('/d/store/test');

        //exists and delete
        $store->set('var', 'value');
        $store->delete('var');

        // missing and delete
        $store->delete('var');
    }
}