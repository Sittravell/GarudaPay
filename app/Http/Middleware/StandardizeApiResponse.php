<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StandardizeApiResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $data = $response->getData(true);
            $statusCode = $response->getStatusCode();

            $status = $statusCode < 400 ? 'success' : 'failed';

            $normalizedData = [
                'status' => $status,
            ];

            // If existing data is array, merge. If validation errors, they usually come as keys.
            // If data is just a message string or null, we might need to structure it.
            // Laravel usually returns array for JsonResponse.

            if (is_array($data)) {
                $normalizedData = array_merge($normalizedData, $data);
            } else {
                $normalizedData['data'] = $data;
            }

            $normalizedData = $this->formatAmounts($normalizedData);

            $response->setData($normalizedData);
        }

        return $response;
    }

    private function formatAmounts(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->formatAmounts($value);
            } elseif (in_array($key, ['amount', 'balance', 'fund', 'converted_amount_usd', 'input_amount'], true) && is_numeric($value)) {
                // "respond with 3 decimal place only"
                $data[$key] = number_format((float) $value, 3, '.', '');
            }
        }
        return $data;
    }
}
