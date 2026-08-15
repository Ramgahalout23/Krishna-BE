<?php

namespace App\Http\Controllers\Api\Traits;

use Illuminate\Http\Request;

/**
 * Maps camelCase request field names to snake_case, so controllers
 * can accept both conventions from the frontend.
 *
 * Usage in a controller method:
 *   $input = $this->mapCamelCase($request->all(), $mappings);
 *   $request->replace($input);
 */
trait MapsCamelCaseFields
{
    /**
     * Map camelCase keys in the input array to snake_case using the given mappings.
     *
     * Keys not present in the mappings array pass through unchanged.
     * Original camelCase keys that have a mapping are removed (only the
     * snake_case version is kept), preventing duplicate values.
     *
     * @param  array  $input    The raw request input (e.g. $request->all())
     * @param  array  $mappings Associative array of 'camelCase' => 'snake_case'
     * @return array            The mapped array with snake_case keys
     */
    protected function mapCamelCase(array $input, array $mappings): array
    {
        $result = [];

        foreach ($input as $key => $value) {
            $snakeKey = $mappings[$key] ?? $key;

            // Keep the key unless it's a camelCase key that was already
            // mapped to a snake_case equivalent (to avoid duplicates).
            if ($snakeKey !== $key || !isset($mappings[$key])) {
                $result[$snakeKey] = $value;
            }
        }

        return $result;
    }
}
