<?php
namespace Cobweb\Svconnector\Service;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Service\AbstractService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

// Define error codes for all Connector services responses
define('T3_ERR_SV_CONNECTION_FAILED', -50); // connection to remote server failed
define('T3_ERR_SV_BAD_RESPONSE', -51); // returned response is malformed or somehow unexpected
define('T3_ERR_SV_DISTANT_ERROR', -52); // returned response contains an error message

/**
 * The "Connector Services" family of services is designed to handle connections
 * to external servers and pass request to and from those servers, using whatever
 * protocols are appropriate.
 *
 * This class is a base class for all Connector Services. It should be inherited
 * by all specific Connector Services implementations. This class should not be called
 * directly as it is unable to do anything by itself.
 *
 * @author Francois Suter (Cobweb) <typo3@cobweb.ch>
 * @package TYPO3
 * @subpackage tx_svconnector
 */
abstract class ConnectorBase extends AbstractService
{
    protected $extKey = 'svconnector'; // The extension key
    protected $parentExtKey = 'svconnector'; // A copy of the extension key so that it is not overridden by children classes
    protected $extConfiguration; // The extension configuration

    /**
     * Verifies that the connection is functional
     * Returns false if not. This base implementation always returns false,
     * since it is not supposed to be called directly
     *
     * @return boolean TRUE if the service is available
     */
    public function init()
    {
        $this->extConfiguration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->parentExtKey]);
        return false;
    }

    /**
     * This method calls the query and returns the results from the response as is.
     *
     * You need to implement your own fetchRaw() method when creating a connector service.
     * It is recommended to place a hook in it, to allow for custom manipulations of the
     * received data.
     * Look at the existing connector services for examples.
     *
     * @param array $parameters Parameters for the call
     * @return mixed Server response
     */
    abstract public function fetchRaw($parameters);

    /**
     * This method calls the query and returns the results from the response as an XML structure.
     *
     * You need to implement your own fetchXML() method when creating a connector service.
     * You can rely on \TYPO3\CMS\Core\Utility\GeneralUtility::array2xml_cs() to convert
     * an array response to XML.
     * It is recommended to place a hook in it, to allow for custom manipulations of the
     * received data.
     * Look at the existing connector services for examples.
     *
     * @param array $parameters Parameters for the call
     * @return string XML structure
     */
    abstract public function fetchXML($parameters);

    /**
     * This method calls the query and returns the results from the response as a PHP array.
     *
     * You need to implement your own fetchArray() method when creating a connector service.
     * You can rely on \tx_svconnector_utility::convertXmlToArray() to convert
     * an XML response to an array.
     * It is recommended to place a hook in it, to allow for custom manipulations of the
     * received data.
     * Look at the existing connector services for examples.
     *
     * @param array $parameters Parameters for the call
     * @return array PHP array
     */
    abstract public function fetchArray($parameters);

    /**
     * This method can be called to perform specific operations at some point after
     * any of the fetch methods have been called. It does nothing by itself,
     * but provides a hook for custom post-processing
     *
     * @param array $parameters Parameters for the call
     * @param mixed $status Some form of status can be passed as argument
     *                      The nature of that status will depend on which process is calling this method
     */
    public function postProcessOperations($parameters, $status)
    {
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][$this->extKey]['postProcessOperations'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][$this->extKey]['postProcessOperations'] as $className) {
                $processor = GeneralUtility::getUserObj($className);
                $processor->postProcessOperations($parameters, $status, $this);
            }
        }
    }

    /**
     * This method queries the distant server given some parameters and returns the server response.
     *
     * You need to implement your own query() method when creating a connector service.
     * It is recommended to put some hooks in your code, because actual use cases from users
     * may differ from your own. A hook to process parameters and a hook to process the response
     * seem useful in general.
     *
     * Look at the existing connector services for implementation examples.
     *
     * @param array $parameters Parameters for the call
     *
     * @return mixed Server response
     */
    abstract protected function query($parameters);

    /**
     * This method should be used by all connector services when they encounter a fatal error
     * It will write the error in the devlog (if activated) and throw an exception
     *
     * @param string $message Error message
     * @param integer $exceptionNumber Number (code) of the exception
     * @param array $extraData Additional data to be passed to the devlog
     * @throws \Exception
     * @return void
     */
    protected function raiseError($message, $exceptionNumber, array $extraData)
    {
        if (!empty($this->extConfiguration['debug'])) {
            GeneralUtility::devLog($message, $this->extKey, 3, $extraData);
        }
        throw new \Exception($message, $exceptionNumber);
    }

    /**
     * Wrapper around the "sL()" method to abstract context.
     *
     * If no language object exists, which should not happen, the key
     * is returned as.
     *
     * @param string $key "LLL:" input key
     * @return string The translated string
     */
    public function sL($key)
    {
        if (TYPO3_MODE === 'FE') {
            return $GLOBALS['TSFE']->sL($key);
        } elseif (isset($GLOBALS['LANG'])) {
            return $GLOBALS['LANG']->sL($key);
        } else {
            return $key;
        }
    }

    /**
     * Gets the currently used character set depending on context.
     *
     * Defaults to UTF-8 if information is not available.
     *
     * @return string
     */
    public function getCharset()
    {
        if (TYPO3_MODE === 'FE') {
            return $GLOBALS['TSFE']->renderCharset;
        } elseif (isset($GLOBALS['LANG'])) {
            return $GLOBALS['LANG']->charSet;
        } else {
            return 'utf-8';
        }
    }

    /**
     * Get an existing instance of the charset conversion class, depending on context.
     *
     * @throws \Exception
     * @return CharsetConverter
     */
    public function getCharsetConverter()
    {
        if (TYPO3_MODE === 'FE') {
            return $GLOBALS['TSFE']->csConvObj;
        } elseif (isset($GLOBALS['LANG'])) {
            return $GLOBALS['LANG']->csConvObj;
        } else {
            throw new \Exception(
                    sprintf('No charset converter available in the current context (%s)', TYPO3_MODE),
                    1396448477
            );
        }
    }
}
