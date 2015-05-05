<?php

/**
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder\Provider;

use Geocoder\Exception\InvalidCredentials;
use Geocoder\Exception\NoResult;
use Geocoder\Exception\UnsupportedOperation;
use Geocoder\Model\AdminLevelCollection;
use Ivory\HttpAdapter\HttpAdapterInterface;

/**
 * @author Tom Laws <tom@ntbti.com>
 */
class GeocoderCA extends AbstractHttpProvider implements LocaleAwareProvider
{

    /**
     * @var string
     */
    //Geocoder DOES offer JSON returns.....unfortunately the errors are returned only as XML :(
    //const GEOCODE_ENDPOINT_URL = '%s://geocoder.ca/?json=1&locate=%s';
    const GEOCODE_ENDPOINT_URL = '%s://geocoder.ca/?geoit=XML&locate=%s';

    use LocaleTrait;

    /**
    * Auth key from http://geocoder.ca/
    * @var string
    */
    private $authKey;

    /**
    *  Use SSL or not?
    *  @var boolean
    */
    private $scheme;

    public function __construct(HttpAdapterInterface $adapter, $authKey, $useSsl = false, $locale=null) {
        parent::__construct($adapter);

        $this->authKey = $authKey;
        $this->scheme = $useSsl ? 'https' : 'http';
        $this->locale = $locale;
    }

    /**
     * {@inheritDoc}
     */
    public function geocode($address) {
        $query = sprintf(self::GEOCODE_ENDPOINT_URL,$this->scheme,urlencode($address));
        if(null !== $this->authKey) {
            //so it looks like you CAN run this without an API key but come on....$1 for 400 queries? Dont be so damn cheap
            //http://geocoder.ca/?terms=1
            $query = sprintf("%s&auth=%s",$query,$this->authKey);
        }
        //$query = sprintf(self::GEOCODE_ENDPOINT_URL,$this->scheme,$this->authKey,urlencode($address));

        return $this->executeQuery($query);
    }

    public function reverse($latitude,$longitude) {
        $address = sprintf("%f,%f",$latitude,$longitude);
        return $this->geocode($address);
    }

    /**
     * {@ingeritDoc}
     */
    public function getName() {
        return 'geocoderca';
    }

    /**
     * @param string $query What to send to the geocoder :)
     */
    public function executeQuery($query) {
        $content = (string)$this->getAdapter()->get($query)->getBody();
        if(empty($content)) {
            throw new NoResult(sprintf("No results from query %s",$query));
        }
        $xml = simplexml_load_string($content);

        if($xml->error) {
            throw new NoResult(sprintf("Error running call: %s -> %s",$xml->error->code,$xml->error->description));
        }

        $results = array();
        $results[] = array_merge($this->getDefaults(), array(
            'latitude' => (float)$xml->latt,
            'longitude' => (float)$xml->longt,
            'postalCode' => (string)$xml->postal,
            'streetNumber' => (string)$xml->standard->stnumber,
            'streetName' => (string)$xml->standard->staddress,
            'locality' => (string)$xml->standard->city,
        ));

        return $this->returnResults($results);
    }
}
