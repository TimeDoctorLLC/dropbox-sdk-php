<?php
namespace Dropbox;

final class AppInfo
{
    /**
     * Your Dropbox <em>app key</em> (OAuth calls this the <em>consumer key</em>).  You can
     * create an app key and secret on the <a href="http://dropbox.com/developers/apps">Dropbox developer website</a>.
     *
     * @returns string
     */
    function getKey() { return $this->key; }

    /** @var string */
    private $key;

    /**
     * Your Dropbox <em>app secret</em> (OAuth calls this the <em>consumer secret</em>).  You can
     * create an app key and secret on the <a href="http://dropbox.com/developers/apps">Dropbox developer website</a>.
     *
     * <p>
     * Make sure that this is kept a secret.  Someone with your app secret can impesonate your
     * application.  People sometimes ask for help on the Dropbox API forums and
     * copy/paste their code, which sometimes includes their app secret.  Do not do that.
     * </p>
     *
     * @return string
     */
    function getSecret() { return $this->secret; }

    /** @var string */
    private $secret;

    /**
     * The type of access your app is configured for.  You can see how your apps are configured
     * on the {@link http://dropbox.com/developers/apps Dropbox developer website}.
     *
     * @return AccessType
     */
    function getAccessType() { return $this->accessType; }

    /** @var string */
    private $accessType;

    /**
     * The set of servers your app will use.
     *
     * @return Host
     */
    function getHost() { return $this->host; }

    /** @var Host */
    private $host;

    /**
     * @param string $key
     * @param string $secret
     * @param string $accessType
     * @param Host $host
     */
    function __construct($key, $secret, $accessType, $host = null)
    {
        Token::checkKeyArg($key);
        Token::checkSecretArg($secret);
        AccessType::checkArg("accessType", $accessType);
        Host::checkArgOrNull("host", $host);

        $this->key = $key;
        $this->secret = $secret;
        $this->accessType = $accessType;

        if ($host === null) {
            $host = Host::getDefault();
        }

        $this->host = $host;
    }

    /**
     * Loads a JSON file containing information about your app. At a minimum, the file must include
     * the key, secret, and access_type fields.  Run 'php authorize.php' in the examples directory
     * for details about what this file should look like.
     *
     * @param string $path Path to a JSON file
     * @returns AppInfo
     */
    static function loadFromJsonFile($path)
    {
        list($jsonArr, $appInfo) = self::loadFromJsonFileWithRaw($path);
        return $appInfo;
    }

    /**
     * Loads a JSON file containing information about your app. At a minimum, the file must include
     * the key, secret, and access_type fields.  Run 'php authorize.php' in the examples directory
     * for details about what this file should look like.
     *
     * @param string $path Path to a JSON file
     * @returns list
     *    A list of two items.  The first is a PHP array representation of the raw JSON, the second
     *    is an AppInfo object that is the parsed version of the JSON.
     */
    static function loadFromJsonFileWithRaw($path)
    {
        if (!file_exists($path)) {
            throw new AppInfoLoadException("File doesn't exist: \"$path\"");
        }

        $str = file_get_contents($path);
        $jsonArr = json_decode($str, TRUE);

        if (is_null($jsonArr)) {
            throw new AppInfoLoadException("JSON parse error: \"$path\"");
        }

        $appInfo = self::loadFromJson($jsonArr);

        return array($jsonArr, $appInfo);
    }

    /**
     *  Parses a JSON object to build an AppInfo object.  If you would like to load this from a file,
     *  use the loadFromJsonFile() method.
     *
     *  @param array $jsonArr Output from json_decode($str, TRUE)
     *  @returns AppInfo
     */
    static function loadFromJson($jsonArr)
    {
        if (!is_array($jsonArr)) {
            throw new AppInfoLoadException("Expecting JSON object, got something else");
        }

        $requiredKeys = array("key", "secret", "access_type");
        foreach ($requiredKeys as $key) {
            if (!isset($jsonArr[$key])) {
                throw new AppInfoLoadException("Missing field \"$key\"");
            }

            if (!is_string($jsonArr[$key])) {
                throw new AppInfoLoadException("Expecting field \"$key\" to be a string");
            }
        }

        // Check app_key and app_secret
        $appKey = $jsonArr["key"];
        $appSecret = $jsonArr["secret"];

        $tokenErr = Token::getTokenPartError($appKey);
        if (!is_null($tokenErr)) {
            throw new AppInfoLoadException("Field \"key\" doesn't look like a valid app key: $tokenErr");
        }

        $tokenErr = Token::getTokenPartError($appSecret);
        if (!is_null($tokenErr)) {
            throw new AppInfoLoadException("Field \"secret\" doesn't look like a valid app secret: $tokenErr");
        }

        // Check the access type
        $accessTypeStr = $jsonArr["access_type"];
        if ($accessTypeStr === "FullDropbox") {
            $accessType = AccessType::FullDropbox();
        }
        else if ($accessTypeStr === "AppFolder") {
            $accessType = AccessType::AppFolder();
        }
        else {
            throw new AppInfoLoadException("Field \"access_type\" must be either \"FullDropbox\" or \"AppFolder\"");
        }

        // Check for the optional 'host' field
        if (!isset($jsonArr["host"])) {
            $host = Host::getDefault();
        }
        else {
            $baseHost = $jsonArr["host"];
            if (!is_string($baseHost)) {
                throw new AppInfoLoadException("Optional field \"host\" must be a string");
            }

            $api = "api-$baseHost";
            $content = "api-content-$baseHost";
            $web = "meta-$baseHost";

            $host = new Host($api, $content, $web);
        }

        return new AppInfo($appKey, $appSecret, $accessType, $host);
    }

    static function checkArg($argName, $argValue)
    {
        if (!($argValue instanceof self)) Checker::throwError($argName, $argValue, __CLASS__);
    }

    static function checkArgOrNull($argName, $argValue)
    {
        if ($argValue === null) return;
        if (!($argValue instanceof self)) Checker::throwError($argName, $argValue, __CLASS__);
    }
}