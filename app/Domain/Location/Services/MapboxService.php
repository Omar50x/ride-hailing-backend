<?php

namespace App\Domain\Location\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class MapboxService
{
    protected string $accessToken;
    protected string $baseUrl = 'https://api.mapbox.com';

    public function __construct()
    {
        $this->accessToken = config('services.mapbox.access_token');
        
        if (empty($this->accessToken)) {
            throw new Exception('Mapbox access token is not configured. Please set MAPBOX_ACCESS_TOKEN in your .env file.');
        }
    }

    /**
     * Geocode an address to coordinates
     * 
     * @param string $address The address to geocode
     * @return array [latitude, longitude]
     * @throws Exception
     */
    public function geocode(string $address): array
    {
        try {
            $encodedAddress = urlencode($address);
            $url = "{$this->baseUrl}/geocoding/v5/mapbox.places/{$encodedAddress}.json";
            
            $response = Http::timeout(10)->get($url, [
                'access_token' => $this->accessToken,
                'limit' => 1,
            ]);

            if (!$response->successful()) {
                Log::error('Mapbox geocoding failed', [
                    'address' => $address,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new Exception('Failed to geocode address: ' . $address);
            }

            $data = $response->json();
            
            if (empty($data['features']) || !isset($data['features'][0]['center'])) {
                throw new Exception('No results found for address: ' . $address);
            }

            // Mapbox returns coordinates as [longitude, latitude]
            $coordinates = $data['features'][0]['center'];
            
            // Return as [latitude, longitude]
            return [$coordinates[1], $coordinates[0]];
        } catch (Exception $e) {
            Log::error('Mapbox geocoding error', [
                'address' => $address,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Reverse geocode coordinates to an address
     * 
     * @param float $latitude
     * @param float $longitude
     * @return string The formatted address
     * @throws Exception
     */
    public function reverseGeocode(float $latitude, float $longitude): string
    {
        try {
            $url = "{$this->baseUrl}/geocoding/v5/mapbox.places/{$longitude},{$latitude}.json";
            
            $response = Http::timeout(10)->get($url, [
                'access_token' => $this->accessToken,
                'limit' => 1,
            ]);

            if (!$response->successful()) {
                Log::error('Mapbox reverse geocoding failed', [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new Exception('Failed to reverse geocode coordinates');
            }

            $data = $response->json();
            
            if (empty($data['features']) || !isset($data['features'][0]['place_name'])) {
                throw new Exception('No results found for coordinates');
            }

            return $data['features'][0]['place_name'];
        } catch (Exception $e) {
            Log::error('Mapbox reverse geocoding error', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Calculate distance and duration between two points using Mapbox Directions API
     * 
     * @param float $lat1
     * @param float $lon1
     * @param float $lat2
     * @param float $lon2
     * @return array ['distance_km' => float, 'duration_min' => int]
     * @throws Exception
     */
    public function getRouteDistanceAndDuration(float $lat1, float $lon1, float $lat2, float $lon2): array
    {
        try {
            $url = "{$this->baseUrl}/directions/v5/mapbox/driving/{$lon1},{$lat1};{$lon2},{$lat2}";
            
            $response = Http::timeout(10)->get($url, [
                'access_token' => $this->accessToken,
                'geometries' => 'geojson',
            ]);

            if (!$response->successful()) {
                Log::error('Mapbox directions failed', [
                    'from' => [$lat1, $lon1],
                    'to' => [$lat2, $lon2],
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new Exception('Failed to get route information');
            }

            $data = $response->json();
            
            if (empty($data['routes']) || !isset($data['routes'][0])) {
                throw new Exception('No route found between points');
            }

            $route = $data['routes'][0];
            
            // Distance is in meters, convert to kilometers
            $distance_km = ($route['distance'] ?? 0) / 1000;
            
            // Duration is in seconds, convert to minutes
            $duration_min = (int) round(($route['duration'] ?? 0) / 60);

            return [
                'distance_km' => round($distance_km, 2),
                'duration_min' => $duration_min,
            ];
        } catch (Exception $e) {
            Log::error('Mapbox directions error', [
                'from' => [$lat1, $lon1],
                'to' => [$lat2, $lon2],
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

