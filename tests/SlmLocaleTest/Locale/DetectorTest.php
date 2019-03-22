<?php
/**
 * Copyright (c) 2012-2013 Jurian Sluiman.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the names of the copyright holders nor the names of the
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @author      Jurian Sluiman <jurian@juriansluiman.nl>
 * @copyright   2012-2013 Jurian Sluiman.
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link        http://juriansluiman.nl
 */
namespace SlmLocaleTest\Locale;

use PHPUnit_Framework_TestCase as TestCase;
use SlmLocale\Locale\Detector;
use SlmLocale\LocaleEvent;

use SlmLocale\Strategy\AbstractStrategy;
use SlmLocale\Strategy\StrategyInterface;
use Zend\EventManager\EventManager;
use Zend\Stdlib\Request;
use Zend\Stdlib\Response;

class DetectorTest extends TestCase
{
    private $events;

    public function setUp()
    {
        $this->events = new EventManager();
    }

    public function testDetectEventUsesLocaleEventObject()
    {
        $detector = new Detector();

        $self = $this;
        $this->setEventManager($detector, LocaleEvent::EVENT_DETECT, function ($e) use ($self) {
            $self->assertInstanceOf(LocaleEvent::class, $e);
        });

        $detector->detect(new Request(), new Response());
    }

    public function testRequestObjectIsSetInDetectEvent()
    {
        $detector = new Detector();
        $request  = new Request();

        $self = $this;
        $this->setEventManager($detector, LocaleEvent::EVENT_DETECT, function ($e) use ($self, $request) {
            $expected = spl_object_hash($request);
            $actual   = spl_object_hash($e->getRequest());

            $self->assertEquals($expected, $actual);
        });

        $detector->detect($request, new Response());
    }

    public function testSupportedLocalesAreDefaultNull()
    {
        $detector = new Detector();

        $self = $this;
        $this->setEventManager($detector, LocaleEvent::EVENT_DETECT, function ($e) use ($self) {
            $self->assertNull($e->getSupported());
        });

        $detector->detect(new Request(), new Response());
    }

    public function testSupportedLocalesAreSetInEvent()
    {
        $detector  = new Detector();
        $supported = ['Foo', 'Bar'];
        $detector->setSupported($supported);

        $self = $this;
        $this->setEventManager($detector, LocaleEvent::EVENT_DETECT, function ($e) use ($self, $supported) {
            $self->assertEquals($supported, $e->getSupported());
        });

        $detector->detect(new Request(), new Response());
    }

    public function testNotShortCircuitedEventReturnsDefaultLocale()
    {
        $detector = new Detector();
        $detector->setDefault('Foo');
        $this->setEventManager($detector);

        $locale = $detector->detect(new Request(), new Response());
        $this->assertEquals('Foo', $locale);
    }

    public function testListenerReturningValueIsAcceptedAsLocale()
    {
        $detector  = new Detector();

        $self = $this;
        $this->setEventManager($detector, LocaleEvent::EVENT_DETECT, function ($e) {
            return 'Foo';
        });

        $locale = $detector->detect(new Request(), new Response());
        $this->assertEquals('Foo', $locale);
    }

    public function testListenerReturningValueIsAcceptedAsLocaleWhenLocaleIsSupported()
    {
        $detector  = new Detector();
        $supported = ['Bar', 'Baz'];
        $detector->setSupported($supported);

        $self = $this;
        $this->setEventManager($detector, LocaleEvent::EVENT_DETECT, function ($e) {
            return 'Bar';
        });

        $locale = $detector->detect(new Request(), new Response());
        $this->assertEquals('Bar', $locale);
    }

    public function testUseDefaultLocaleWhenResultIsNotSupported()
    {
        $detector  = new Detector();
        $supported = ['Bar', 'Baz'];
        $detector->setSupported($supported);
        $detector->setDefault('Foo');

        $self = $this;
        $this->setEventManager($detector, LocaleEvent::EVENT_DETECT, function ($e) {
            return 'Bat';
        });

        $locale = $detector->detect(new Request(), new Response());
        $this->assertEquals('Foo', $locale);
    }

    public function testEmptySupportedListIndicatesNoSupportedList()
    {
        $detector  = new Detector();
        $supported = [];
        $detector->setSupported($supported);

        $this->assertFalse($detector->hasSupported());
    }

    public function testUseMappedLocaleWhenMappingIsDefined()
    {
        $detector = new Detector();
        $mappings = ['Foo' => 'Bar'];
        $detector->setMappings($mappings);

        $self = $this;
        $this->setEventManager($detector, LocaleEvent::EVENT_DETECT, function ($e) {
            return 'Foo';
        });

        $locale = $detector->detect(new Request(), new Response());
        $this->assertEquals('Bar', $locale);
    }

    public function testEmptyMappingsListIndicatesNoMappingsList()
    {
        $detector = new Detector();
        $mappings = [];
        $detector->setMappings($mappings);

        $this->assertFalse($detector->hasMappings());
    }

    public function testStrategyAttachesToEventManager()
    {
        $detector = new Detector();
        $events   = $this->createMock(EventManager::class);
        $strategy = $this->createMock(StrategyInterface::class);
        $strategy->expects($this->once())
            ->method('attach')
            ->with($events);

        $detector->setEventManager($events);
        $detector->addStrategy($strategy);
    }

    public function testStrategyWithHighestPriorityWins()
    {
        $detector  = new Detector();
        $this->setEventManager($detector);

        $strategy1 = $this
            ->getMockBuilder(AbstractStrategy::class)
            ->setMethods(['detect'])
            ->getMock();
        $strategy1->expects($this->once())
                  ->method('detect')
                  ->will($this->returnValue('Foo'));

        $strategy2 = $this
            ->getMockBuilder(AbstractStrategy::class)
            ->setMethods(['detect'])
            ->getMock();
        $strategy2->expects($this->never())
                  ->method('detect');

        $detector->addStrategy($strategy1, 10);
        $detector->addStrategy($strategy2, 1);

        $locale = $detector->detect(new Request(), new Response());
        $this->assertEquals('Foo', $locale);
    }

    public function testFoundEventUsesLocaleEventObject()
    {
        $detector = new Detector();

        $self = $this;
        $this->setEventManager($detector, LocaleEvent::EVENT_FOUND, function ($e) use ($self) {
            $self->assertInstanceOf(LocaleEvent::class, $e);
        });

        $detector->detect(new Request(), new Response());
    }

    public function testRequestObjectIsSetInFoundEvent()
    {
        $detector = new Detector();
        $request  = new Request();

        $self = $this;
        $this->setEventManager($detector, LocaleEvent::EVENT_FOUND, function ($e) use ($self, $request) {
            $expected = spl_object_hash($request);
            $actual   = spl_object_hash($e->getRequest());

            $self->assertEquals($expected, $actual);
        });

        $detector->detect($request, new Response());
    }

    public function testResponseObjectIsSetInFoundEvent()
    {
        $detector = new Detector();
        $response = new Response();

        $self = $this;
        $this->setEventManager($detector, LocaleEvent::EVENT_FOUND, function ($e) use ($self, $response) {
            $expected = spl_object_hash($response);
            $actual   = spl_object_hash($e->getResponse());

            $self->assertEquals($expected, $actual);
        });

        $detector->detect(new Request(), $response);
    }

    public function testResponseObjectIsReturnedIfModified()
    {
        $detector = new Detector();
        $response = new Response();

        $this->setEventManager($detector, LocaleEvent::EVENT_FOUND, function ($e) {
            $response = $e->getResponse();

            // lets pretend we modified response

            return $response;
        });

        $result = $detector->detect(new Request(), $response);
        $this->assertInstanceOf(Response::class, $result);
    }

    public function testResponseObjectCanBeModifiedByMultipleStrategies()
    {
        $detector = new Detector();
        $response = new Response();

        $this->setEventManager($detector, LocaleEvent::EVENT_FOUND, function ($e) {
            $response = $e->getResponse();
            $response->setContent('first');

            return $response;
        });
        $this->events->attach(LocaleEvent::EVENT_FOUND, function ($e) {
            $response = $e->getResponse();
            $response->setContent($response->getContent() . 'second');

            return $response;
        });

        $result = $detector->detect(new Request(), $response);
        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals('firstsecond', $result->getContent());
    }

    public function testLocaleIsSetInFoundEvent()
    {
        $detector = new Detector();
        $detector->setDefault('Foo');

        $self = $this;
        $this->setEventManager($detector, LocaleEvent::EVENT_FOUND, function ($e) use ($self) {
            $self->assertEquals('Foo', $e->getLocale());
        });

        $detector->detect(new Request(), new Response());
    }

    public function setEventManager(Detector $detector, $event = null, $callback = null)
    {
        if (null !== $event && null !== $callback) {
            $this->events->attach($event, $callback);
        }

        $detector->setEventManager($this->events);

        return $detector;
    }
}
