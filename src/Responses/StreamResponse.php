<?php

namespace OpenAI\Responses;

use Generator;
use OpenAI\Contracts\ResponseHasMetaInformationContract;
use OpenAI\Contracts\ResponseStreamContract;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Responses\Meta\MetaInformation;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * @template TResponse
 *
 * @implements ResponseStreamContract<TResponse>
 */
final class StreamResponse implements ResponseHasMetaInformationContract, ResponseStreamContract
{
    /**
     * Creates a new Stream Response instance.
     *
     * @param  class-string<TResponse>  $responseClass
     */
    public function __construct(
        private readonly string $responseClass,
        private readonly ResponseInterface $response,
    ) {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator(): Generator
    {
        while (! $this->response->getBody()->eof()) {
            $line = $this->readLine($this->response->getBody());

            // if (! str_starts_with($line, 'data:')) {
            //     continue;
            // }

            // $data = substr($line, strlen('data: '));
            $data = $line;

            if ($data === '<END_STREAM_SSE>') {
                break;
            }

            /** @var array{error?: array{message: string|array<int, string>, type: string, code: string}} $response */
            $response = [
                "warning" => "This model version is deprecated. Migrate before January 4, 2024 to avoid disruption of service. Learn more https://platform.openai.com/docs/deprecations",
                "id" => "cmpl-8bAyz9ojKp72oedPIGhtwagex8w1Q",
                "object" => "text_completion",
                "created" => time(),
                "choices" =>  [
                    0 => [
                        "text" => $data,
                        "index" => 0,
                        "logprobs" => null,
                        "finish_reason" => null,
                    ],
                ],
                "model" => "imtabot",
            ];

            // if (isset($response['error'])) {
            //     throw new ErrorException($response['error']);
            // }

            yield $this->responseClass::from($response);
        }
    }

    /**
     * Read a line from the stream.
     */
    private function readLine(StreamInterface $stream): mixed
    {
        $buffer = '';

        while (! $stream->eof()) {
            $byte = $stream->read(1);
            // if ('' === ($byte = $stream->read(1))) {
            //     return $buffer;
            // }

            $buffer .= $byte;
            // if ($byte === "\n") {
            //     break;
            // }
            if (str_contains($buffer, 'retry')) {
                break;
            }
        }

        $re = '/data: (.*?[\r|\n])/m';
        preg_match_all($re, $buffer, $matches, PREG_SET_ORDER, 0);
        if (count($matches) == 1) {
            return $matches[0][1];
        }

        $buffer = array_reduce($matches, function($carry, $item) {
            $carry[] = $item[1];
            return $carry;
        }, []);
        return $buffer;
    }

    public function meta(): MetaInformation
    {
        // @phpstan-ignore-next-line
        return MetaInformation::from($this->response->getHeaders());
    }
}
