<?php

namespace spec\Http\Client\Common\HttpClientPool;

use Http\Client\Common\HttpClientPoolItem;
use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient;
use Http\Promise\Promise;
use PhpSpec\Exception\Example\SkippingException;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class LeastUsedClientPoolSpec extends ObjectBehavior
{
    public function it_is_initializable()
    {
        $this->shouldHaveType('Http\Client\Common\HttpClientPool\LeastUsedClientPool');
    }

    public function it_is_an_http_client()
    {
        $this->shouldImplement('Http\Client\HttpClient');
    }

    public function it_is_an_async_http_client()
    {
        $this->shouldImplement('Http\Client\HttpAsyncClient');
    }

    public function it_throw_exception_with_no_client(RequestInterface $request)
    {
        $this->shouldThrow('Http\Client\Common\Exception\HttpClientNotFoundException')->duringSendRequest($request);
        $this->shouldThrow('Http\Client\Common\Exception\HttpClientNotFoundException')->duringSendAsyncRequest($request);
    }

    public function it_sends_request(HttpClient $httpClient, RequestInterface $request, ResponseInterface $response)
    {
        $this->addHttpClient($httpClient);
        $httpClient->sendRequest($request)->willReturn($response);

        $this->sendRequest($request)->shouldReturn($response);
    }

    public function it_sends_async_request(HttpAsyncClient $httpAsyncClient, RequestInterface $request, Promise $promise)
    {
        $this->addHttpClient($httpAsyncClient);
        $httpAsyncClient->sendAsyncRequest($request)->willReturn($promise);
        $promise->then(Argument::type('callable'), Argument::type('callable'))->willReturn($promise);

        $this->sendAsyncRequest($request)->shouldReturn($promise);
    }

    public function it_throw_exception_if_no_more_enable_client(HttpClient $client, RequestInterface $request)
    {
        $this->addHttpClient($client);
        $client->sendRequest($request)->willThrow('Http\Client\Exception\HttpException');

        $this->shouldThrow('Http\Client\Exception\HttpException')->duringSendRequest($request);
        $this->shouldThrow('Http\Client\Common\Exception\HttpClientNotFoundException')->duringSendRequest($request);
    }

    public function it_reenable_client(HttpClient $client, RequestInterface $request)
    {
        $this->addHttpClient(new HttpClientPoolItem($client->getWrappedObject(), 0));
        $client->sendRequest($request)->willThrow('Http\Client\Exception\HttpException');

        $this->shouldThrow('Http\Client\Exception\HttpException')->duringSendRequest($request);
        $this->shouldThrow('Http\Client\Exception\HttpException')->duringSendRequest($request);
    }

    public function it_uses_the_lowest_request_client(HttpClientPoolItem $client1, HttpClientPoolItem $client2, RequestInterface $request, ResponseInterface $response)
    {
        if (extension_loaded('xdebug')) {
            throw new SkippingException('This test fail when xdebug is enable on PHP < 7');
        }

        $this->addHttpClient($client1);
        $this->addHttpClient($client2);

        $client1->getSendingRequestCount()->willReturn(10);
        $client2->getSendingRequestCount()->willReturn(2);

        $client1->isDisabled()->willReturn(false);
        $client2->isDisabled()->willReturn(false);

        $client1->sendRequest($request)->shouldNotBeCalled();
        $client2->sendRequest($request)->willReturn($response);

        $this->sendRequest($request)->shouldReturn($response);
    }
}
