<?php

declare(strict_types=1);

namespace App\Mcp\Methods;

use Generator;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Methods\CallTool as BaseCallTool;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\ToolInvoker;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

final class CallTool extends ToolInvoker implements Method
{
    /**
     * @return JsonRpcResponse|Generator<JsonRpcResponse>
     *
     * @throws JsonRpcException
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        $tool = $context->tools()->first(fn (Tool $tool): bool => $tool->name() === $request->get('name'));

        if (! $tool instanceof Tool) {
            return new BaseCallTool()->handle($request, $context);
        }

        $problem = $this->unknownArguments($tool, $request->get('arguments', []));

        return $problem === null
            ? $this->invoke($tool, $request)
            : $this->toJsonRpcResponse($request, Response::error($problem), $this->serializable($tool));
    }

    private function unknownArguments(Tool $tool, mixed $arguments): ?string
    {
        $schema = $tool->toArray()['inputSchema'];
        $properties = array_keys((array) ($schema['properties'] ?? []));
        $unknown = array_values(array_diff(array_map(strval(...), is_array($arguments) ? array_keys($arguments) : []), $properties));

        if ($unknown === []) {
            return null;
        }

        $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];
        $accepted = array_map(fn (string $property): string => in_array($property, $required, true) ? "{$property} (required)" : $property, $properties);
        $names = implode(', ', array_map(fn (string $key): string => "\"{$key}\"", $unknown));

        return $tool->name().' has no '.$names.' '.(count($unknown) === 1 ? 'argument' : 'arguments').'. '
            .($accepted === [] ? 'It takes no arguments.' : 'It takes: '.implode(', ', $accepted).'.');
    }
}
