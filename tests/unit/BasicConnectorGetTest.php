<?php

/**
 * Copyright 2012 Klarna AB
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * File containing the Klarna_Checkout_BasicConnector (GET) unittest
 *
 * PHP version 5.3
 *
 * @category  Payment
 * @package   Klarna_Checkout
 * @author    Klarna <support@klarna.com>
 * @copyright 2012 Klarna AB
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache license v2.0
 * @link      http://integration.klarna.com/
 */

/**
 * GET UnitTest for the Connector class
 *
 * @category  Payment
 * @package   Klarna_Checkout
 * @author    Rickard D. <rickard.dybeck@klarna.com>
 * @author    Christer G. <christer.gustavsson@klarna.com>
 * @copyright 2012 Klarna AB
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache license v2.0
 * @link      http://integration.klarna.com/
 */
class Klarna_Checkout_BasicConnectorGetTest extends PHPUnit_Framework_TestCase
{

    /**
     * Stubbed Order Object
     *
     * @var Klarna_Checkout_ResourceInterface
     */
    public $orderStub;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp()
    {
        $this->orderStub = new Klarna_Checkout_ResourceStub;

        $this->digest = $this->getMock(
            'Klarna_Checkout_Digest', array('create')
        );
    }

    /**
     * Test apply with a 200 code
     *
     * @return void
     */
    public function testApplyGet200()
    {
        $curl = new Klarna_Checkout_HTTP_TransportStub;

        $payload = '{"flobadob":["bobcat","wookie"]}';
        $data = array(
            'code' => 200,
            'headers' => array(),
            'payload' => $payload
        );
        $curl->addResponse($data);

        $expectedDigest = 'stnaeu\eu2341aoaaoae==';

        $this->digest->expects($this->once())
            ->method('create')
            ->with('aboogie')
            ->will($this->returnValue($expectedDigest));

        $object = new Klarna_Checkout_BasicConnector(
            $curl,
            $this->digest,
            'aboogie'
        );
        $result = $object->apply('GET', $this->orderStub);

        $this->assertEquals($payload, $result->getData(), 'Response payload');
        $this->assertEquals(
            "Klarna {$expectedDigest}",
            $curl->request->getHeader('Authorization'),
            'Header'
        );

        $this->assertEquals(
            json_decode($payload, true),
            $this->orderStub->marshal(),
            'Content'
        );

        $this->assertEquals(
            $this->orderStub->getContentType(),
            $curl->request->getHeader('Accept'),
            'Accept Content Type'
        );
    }

    /**
     * Test so apply with a 200 code but an invalid json response throws an
     * exception.
     *
     * @return void
     */
    public function testApplyGet200InvalidJSON()
    {
        $this->setExpectedException('Klarna_Checkout_ConnectorException');

        $curl = new Klarna_Checkout_HTTP_TransportStub;

        $payload = '{"flobadob"}';
        $data = array(
            'code' => 200,
            'headers' => array(),
            'payload' => $payload
        );
        $curl->addResponse($data);

        $object = new Klarna_Checkout_BasicConnector(
            $curl,
            $this->digest,
            'secret'
        );
        $object->apply('GET', $this->orderStub);
    }

    /**
     * Test with url option, to ensure it gets picked up
     *
     * @return void
     */
    public function testApplyWithUrlInOptions()
    {
        $options = array('url' => 'localhost');

        $curl = new Klarna_Checkout_HTTP_TransportStub;

        $payload = '{"flobadob":["bobcat","wookie"]}';

        $data = array(
            'code' => 200,
            'headers' => array(),
            'payload' => $payload
        );
        $curl->addResponse($data);

        $this->digest->expects($this->once())
            ->method('create')
            ->with('aboogie')
            ->will($this->returnValue('stnaeu\eu2341aoaaoae=='));

        $object = new Klarna_Checkout_BasicConnector(
            $curl,
            $this->digest,
            'aboogie'
        );
        $result = $object->apply('GET', $this->orderStub, $options);

        $request = $result->getRequest();

        $this->assertEquals($options['url'], $request->getUrl(), 'Url Option');
    }

    /**
     * Test with a redirect (301) to a OK (200)
     *
     * @return void
     */
    public function testApplyGet301to200()
    {
        $options = array('url' => 'localhost');

        $curl = new Klarna_Checkout_HTTP_TransportStub;

        $payload = '{"flobadob":["bobcat","wookie"]}';
        $redirect = 'not localhost';
        $data = array(
            array(
                'code' => 200,
                'headers' => array(),
                'payload' => $payload
            ),
            array(
                'code' => 301,
                'headers' => array('Location' => $redirect),
                'payload' => $payload
            )
        );
        foreach ($data as $response) {
            $curl->addResponse($response);
        }

        $this->digest->expects($this->any())
            ->method('create')
            ->with('aboogie')
            ->will($this->returnValue('stnaeu\eu2341aoaaoae=='));

        $object = new Klarna_Checkout_BasicConnector(
            $curl,
            $this->digest,
            'aboogie'
        );
        $result = $object->apply('GET', $this->orderStub, $options);

        $request = $result->getRequest();

        $this->assertEquals($redirect, $request->getUrl(), 'Url Option');
    }

    /**
     * Test with a redirect (302) to a Forbidden (503) to ensure exception is
     * thrown.
     *
     * @return void
     */
    public function testApplyGet302to503()
    {
        $this->setExpectedException(
            'Klarna_Checkout_ConnectorException', 'Forbidden', 503
        );

        $options = array('url' => 'localhost');

        $curl = new Klarna_Checkout_HTTP_TransportStub;

        $payload = 'Forbidden';
        $redirect = 'not localhost';
        $data = array(
            array(
                'code' => 503,
                'headers' => array(),
                'payload' => $payload
            ),
            array(
                'code' => 301,
                'headers' => array('Location' => $redirect),
                'payload' => ""
            )
        );
        foreach ($data as $response) {
            $curl->addResponse($response);
        }

        $object = new Klarna_Checkout_BasicConnector(
            $curl,
            $this->digest,
            'aboogie'
        );

        $result = null;
        try {
            $result = $object->apply('GET', $this->orderStub, $options);
        } catch (Exception $e) {
            $request = $curl->request;
            $this->assertEquals($redirect, $request->getUrl(), 'Url Option');
            throw $e;
        }
    }

    /**
     * Test with an infinite redirect (301) loop.
     *
     * @return void
     */
    public function testApplyGet301InfiniteLoop()
    {
        $this->setExpectedException(
            'Klarna_Checkout_ConnectorException',
            'Infinite redirect loop detected.'
        );

        $options = array('url' => 'localhost');

        $curl = new Klarna_Checkout_HTTP_TransportStub;

        $curl->addResponse(
            array(
                'code' => 301,
                'headers' => array('Location' => 'not localhost'),
                'payload' => ""
            )
        );

        $object = new Klarna_Checkout_BasicConnector(
            $curl,
            $this->digest,
            'aboogie'
        );

        $object->apply('GET', $this->orderStub, $options);
    }
}
