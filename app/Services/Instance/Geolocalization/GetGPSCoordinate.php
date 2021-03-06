<?php

namespace App\Services\Instance\Geolocalization;

use App\Models\Account\Place;
use App\Services\BaseService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;

class GetGPSCoordinate extends BaseService
{
    /**
     * Get the validation rules that apply to the service.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'account_id' => 'required|integer|exists:accounts,id',
            'place_id' => 'required|integer|exists:places,id',
        ];
    }

    /**
     * Get the latitude and longitude from a place.
     * This method uses LocationIQ to process the geocoding.
     *
     * @param array $data
     * @return Place|null
     */
    public function execute(array $data)
    {
        $this->validate($data);

        $place = Place::where('account_id', $data['account_id'])
            ->findOrFail($data['place_id']);

        return $this->query($place);
    }

    /**
     * Build the query to send with the API call.
     *
     * @param Place $place
     * @return string|null
     */
    private function getQuery(Place $place)
    {
        if (! config('monica.enable_geolocation')) {
            return;
        }

        if (is_null(config('monica.location_iq_api_key'))) {
            return;
        }

        $query = 'https://us1.locationiq.com/v1/search.php?key=';
        $query .= config('monica.location_iq_api_key');
        $query .= '&q=';
        $query .= urlencode($place->getAddressAsString());
        $query .= '&format=json';

        return $query;
    }

    /**
     * Actually make the call to the reverse geocoding API.
     *
     * @param Place $place
     * @return Place|null
     */
    private function query(Place $place)
    {
        $query = $this->getQuery($place);

        if (is_null($query)) {
            return;
        }

        $client = new GuzzleClient();

        try {
            $response = $client->request('GET', $query);
        } catch (ClientException $e) {
            return;
        }

        $response = json_decode($response->getBody());

        $place->latitude = $response[0]->lat;
        $place->longitude = $response[0]->lon;
        $place->save();

        return $place;
    }
}
