<?php
namespace Msgframework\Lib\Language;

use Joomla\String\StringHelper;

/**
 * Languages/translation handler class
 *
 * @since  1.0.0
 */
class Language
{

    /**
     * Debug language, If true, highlights if string isn't found.
     *
     * @var    LanguageFactoryInterface
     * @since  1.0.0
     */
    protected LanguageFactoryInterface $factory;

    /**
     * Debug language, If true, highlights if string isn't found.
     *
     * @var    boolean
     * @since  1.0.0
     */
    protected bool $debug = false;

    /**
     * Debug language keys, If true, system show keys .
     *
     * @var    boolean
     * @since  1.0.0
     */
    protected bool $debugKeys = false;

    /**
     * The default language, used when a language file in the requested language does not exist.
     *
     * @var    string
     * @since  1.0.0
     */
    protected string $default = 'ru-RU';

    /**
     * An array of orphaned text.
     *
     * @var    array
     * @since  1.0.0
     */
    protected array $orphans = array();

    /**
     * Array holding the language metadata.
     *
     * @var    array
     * @since  1.0.0
     */
    protected array $metadata;

    /**
     * Array holding the language locale or boolean null if none.
     *
     * @var    array|null
     * @since  1.0.0
     */
    protected ?array $locale = null;

    /**
     * The language to load.
     *
     * @var    string
     * @since  1.0.0
     */
    protected string $lang;

    /**
     * A nested array of language files that have been loaded
     *
     * @var    array
     * @since  1.0.0
     */
    protected array $paths = array();

    /**
     * List of language files that are in error state
     *
     * @var    array
     * @since  1.0.0
     */
    protected array $errorfiles = array();

    /**
     * Translations
     *
     * @var    array
     * @since  1.0.0
     */
    protected array $strings = array();

    /**
     * An array of used text, used during debugging.
     *
     * @var    array
     * @since  1.0.0
     */
    protected array $used = array();

    /**
     * Counter for number of loads.
     *
     * @var    integer
     * @since  1.0.0
     */
    protected int $counter = 0;

    /**
     * An array used to store overrides.
     *
     * @var    array
     * @since  1.0.0
     */
    protected array $override = array();

    /**
     * Name of the transliterator function for this language.
     *
     * @var    callable
     * @since  1.0.0
     */
    protected $transliterator;

    /**
     * Name of the pluralSuffixesCallback function for this language.
     *
     * @var    callable
     * @since  1.0.0
     */
    protected $pluralSuffixesCallback = null;

    /**
     * Name of the ignoredSearchWordsCallback function for this language.
     *
     * @var    callable
     * @since  1.0.0
     */
    protected $ignoredSearchWordsCallback = null;

    /**
     * Name of the lowerLimitSearchWordCallback function for this language.
     *
     * @var    callable
     * @since  1.0.0
     */
    protected $lowerLimitSearchWordCallback = null;

    /**
     * Name of the upperLimitSearchWordCallback function for this language.
     *
     * @var    callable
     * @since  1.0.0
     */
    protected $upperLimitSearchWordCallback = null;

    /**
     * Name of the searchDisplayedCharactersNumberCallback function for this language.
     *
     * @var    callable
     * @since  1.0.0
     */
    protected $searchDisplayedCharactersNumberCallback = null;

    /**
     * Constructor activating the default information of the language.
     *
     * @param string|null $lang   The language
     * @param boolean $debug  Indicates if language debugging is enabled.
     *
     * @since   1.0.0
     */
    public function __construct(LanguageFactoryInterface $factory, string $lang = null, bool $debug = false)
    {
        $this->factory = $factory;
        $this->strings = array();

        if ($lang == null)
        {
            $lang = $this->default;
        }

        $this->lang = $lang;
        $this->setMetaData();
        $this->setDebug($debug);

        $app = $this->factory->getApplication();

        $dir = $app->getDir();

        /*
         * Let's load the default override once, so we can profit from that, too
         * But make sure, that we don't enforce it on each language file load.
         * So don't put it in $this->override
         */
        if (!$this->debug && $lang !== $this->default)
        {
            $this->loadLanguage($dir . '/language/overrides/' . $this->default . '.override.ini');
        }

        $this->override = $this->parse($dir . '/language/overrides/' . $lang . '.override.ini');

        // Look for a language specific localise class
        $class = str_replace('-', '_', $lang . 'Localise');
        $paths = array();

        // Note: Manual indexing to enforce load order.
        $paths[0] = $dir . "/language/overrides/$lang.localise.php";
        $paths[2] = $dir . "/language/$lang/localise.php";

        ksort($paths);
        $path = reset($paths);

        while (!class_exists($class) && $path)
        {
            if (is_file($path))
            {
                require_once $path;
            }

            $path = next($paths);
        }

        if (class_exists($class))
        {
            /**
             * Class exists. Try to find
             * -a transliterate method,
             * -a getPluralSuffixes method,
             * -a getIgnoredSearchWords method
             * -a getLowerLimitSearchWord method
             * -a getUpperLimitSearchWord method
             * -a getSearchDisplayCharactersNumber method
             */
            if (method_exists($class, 'transliterate'))
            {
                $this->transliterator = array($class, 'transliterate');
            }

            if (method_exists($class, 'getPluralSuffixes'))
            {
                $this->pluralSuffixesCallback = array($class, 'getPluralSuffixes');
            }

            if (method_exists($class, 'getIgnoredSearchWords'))
            {
                $this->ignoredSearchWordsCallback = array($class, 'getIgnoredSearchWords');
            }

            if (method_exists($class, 'getLowerLimitSearchWord'))
            {
                $this->lowerLimitSearchWordCallback = array($class, 'getLowerLimitSearchWord');
            }

            if (method_exists($class, 'getUpperLimitSearchWord'))
            {
                $this->upperLimitSearchWordCallback = array($class, 'getUpperLimitSearchWord');
            }

            if (method_exists($class, 'getSearchDisplayedCharactersNumber'))
            {
                $this->searchDisplayedCharactersNumberCallback = array($class, 'getSearchDisplayedCharactersNumber');
            }
        }

        $this->load('system', $dir);
    }

    /**
     * Translate function, mimics the php gettext (alias _) function.
     *
     * The function checks if $jsSafe is true, then if $interpretBackslashes is true.
     *
     * @param string $string                The string to translate
     * @param boolean $jsSafe                Make the result javascript safe
     * @param boolean $interpretBackSlashes  Interpret \t and \n
     *
     * @return  string  The translation of the string
     *
     * @since   1.0.0
     */
    public function _(string $string, bool $jsSafe = false, bool $interpretBackSlashes = true): string
    {
        // Detect empty string
        if ($string == '')
        {
            return '';
        }

        $key = strtoupper($string);

        if (isset($this->strings[$key]))
        {
            $string = $this->strings[$key];

            // Store debug information
            if ($this->debug)
            {
                $value = $this->debugKeys ? $string : $key;
                $string = '**' . $value . '**';

                $caller = $this->getCallerInfo();

                if (!\array_key_exists($key, $this->used))
                {
                    $this->used[$key] = array();
                }

                $this->used[$key][] = $caller;
            }
        }
        else
        {
            if ($this->debug)
            {
                $info = [];
                $info['trace'] = $this->getTrace();
                $info['key'] = $key;
                $info['string'] = $string;

                if (!\array_key_exists($key, $this->orphans))
                {
                    $this->orphans[$key] = array();
                }

                $this->orphans[$key][] = $info;

                $string = '??' . $string . '??';
            }
        }

        if ($jsSafe)
        {
            // Javascript filter
            $string = addslashes($string);
        }
        elseif ($interpretBackSlashes)
        {
            if (strpos($string, '\\') !== false)
            {
                // Interpret \n and \t characters
                $string = str_replace(array('\\\\', '\t', '\n'), array("\\", "\t", "\n"), $string);
            }
        }

        return $string;
    }

    protected function setMetaData()
    {
        $app = $this->factory->getApplication();

        $path =  LanguageHelper::getLanguagePath($app->getDir(), $this->lang) . DIRECTORY_SEPARATOR . 'metadata.json';

        $this->metadata = LanguageHelper::parseMetadataFile($path);
    }

    /**
     * Transliterate function
     *
     * This method processes a string and replaces all accented UTF-8 characters by unaccented
     * ASCII-7 "equivalents".
     *
     * @param string $string  The string to transliterate.
     *
     * @return  string  The transliteration of the string.
     *
     * @since   1.0.0
     */
    public function transliterate(string $string): string
    {
        // First check for transliterator provided by translation
        if ($this->transliterator !== null)
        {
            $string = \call_user_func($this->transliterator, $string);

            // Check if all symbols were transliterated (contains only ASCII), otherwise continue
            if (!preg_match('/[\\x80-\\xff]/', $string))
            {
                return $string;
            }
        }

        // Run our transliterator for common symbols,
        // This need to be executed before native php transliterator, because it may not have all required transliterators
        $string = Transliterate::utf8_latin_to_ascii($string);

        // Check if all symbols were transliterated (contains only ASCII),
        // Otherwise try to use native php function if available
        if (preg_match('/[\\x80-\\xff]/', $string) && function_exists('transliterator_transliterate') && function_exists('iconv'))
        {
            return iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $string));
        }

        return StringHelper::strtolower($string);
    }

    /**
     * Getter for transliteration function
     *
     * @return  callable  The transliterator function
     *
     * @since   1.0.0
     */
    public function getTransliterator(): callable
    {
        return $this->transliterator;
    }

    /**
     * Set the transliteration function.
     *
     * @param   callable  $function  Function name or the actual function.
     *
     * @return  callable  The previous function.
     *
     * @since   1.0.0
     */
    public function setTransliterator(callable $function): callable
    {
        $previous = $this->transliterator;
        $this->transliterator = $function;

        return $previous;
    }

    /**
     * Returns an array of suffixes for plural rules.
     *
     * @param integer $count  The count number the rule is for.
     *
     * @return  array    The array of suffixes.
     *
     * @since   1.0.0
     */
    public function getPluralSuffixes(int $count): array
    {
        if ($this->pluralSuffixesCallback !== null)
        {
            return \call_user_func($this->pluralSuffixesCallback, $count);
        }
        else
        {
            return array((string) $count);
        }
    }

    /**
     * Getter for pluralSuffixesCallback function.
     *
     * @return  callable  Function name or the actual function.
     *
     * @since   1.0.0
     */
    public function getPluralSuffixesCallback(): callable
    {
        return $this->pluralSuffixesCallback;
    }

    /**
     * Set the pluralSuffixes function.
     *
     * @param   callable  $function  Function name or actual function.
     *
     * @return  callable  The previous function.
     *
     * @since   1.0.0
     */
    public function setPluralSuffixesCallback(callable $function): callable
    {
        $previous = $this->pluralSuffixesCallback;
        $this->pluralSuffixesCallback = $function;

        return $previous;
    }

    /**
     * Returns an array of ignored search words
     *
     * @return  array  The array of ignored search words.
     *
     * @since   1.0.0
     */
    public function getIgnoredSearchWords(): array
    {
        if ($this->ignoredSearchWordsCallback !== null)
        {
            return \call_user_func($this->ignoredSearchWordsCallback);
        }
        else
        {
            return array();
        }
    }

    /**
     * Getter for ignoredSearchWordsCallback function.
     *
     * @return  callable  Function name or the actual function.
     *
     * @since   1.0.0
     */
    public function getIgnoredSearchWordsCallback(): callable
    {
        return $this->ignoredSearchWordsCallback;
    }

    /**
     * Setter for the ignoredSearchWordsCallback function
     *
     * @param   callable  $function  Function name or actual function.
     *
     * @return  callable  The previous function.
     *
     * @since   1.0.0
     */
    public function setIgnoredSearchWordsCallback(callable $function): callable
    {
        $previous = $this->ignoredSearchWordsCallback;
        $this->ignoredSearchWordsCallback = $function;

        return $previous;
    }

    /**
     * Returns a lower limit integer for length of search words
     *
     * @return  integer  The lower limit integer for length of search words (3 if no value was set for a specific language).
     *
     * @since   1.0.0
     */
    public function getLowerLimitSearchWord(): int
    {
        if ($this->lowerLimitSearchWordCallback !== null)
        {
            return \call_user_func($this->lowerLimitSearchWordCallback);
        }
        else
        {
            return 3;
        }
    }

    /**
     * Getter for lowerLimitSearchWordCallback function
     *
     * @return  callable  Function name or the actual function.
     *
     * @since   1.0.0
     */
    public function getLowerLimitSearchWordCallback(): callable
    {
        return $this->lowerLimitSearchWordCallback;
    }

    /**
     * Setter for the lowerLimitSearchWordCallback function.
     *
     * @param   callable  $function  Function name or actual function.
     *
     * @return  callable  The previous function.
     *
     * @since   1.0.0
     */
    public function setLowerLimitSearchWordCallback(callable $function): callable
    {
        $previous = $this->lowerLimitSearchWordCallback;
        $this->lowerLimitSearchWordCallback = $function;

        return $previous;
    }

    /**
     * Returns an upper limit integer for length of search words
     *
     * @return  integer  The upper limit integer for length of search words (200 if no value was set or if default value is < 200).
     *
     * @since   1.0.0
     */
    public function getUpperLimitSearchWord(): int
    {
        if ($this->upperLimitSearchWordCallback !== null && \call_user_func($this->upperLimitSearchWordCallback) > 200)
        {
            return \call_user_func($this->upperLimitSearchWordCallback);
        }

        return 200;
    }

    /**
     * Getter for upperLimitSearchWordCallback function
     *
     * @return  callable  Function name or the actual function.
     *
     * @since   1.0.0
     */
    public function getUpperLimitSearchWordCallback(): callable
    {
        return $this->upperLimitSearchWordCallback;
    }

    /**
     * Setter for the upperLimitSearchWordCallback function
     *
     * @param   callable  $function  Function name or the actual function.
     *
     * @return  callable  The previous function.
     *
     * @since   1.0.0
     */
    public function setUpperLimitSearchWordCallback(callable $function): callable
    {
        $previous = $this->upperLimitSearchWordCallback;
        $this->upperLimitSearchWordCallback = $function;

        return $previous;
    }

    /**
     * Returns the number of characters displayed in search results.
     *
     * @return  integer  The number of characters displayed (200 if no value was set for a specific language).
     *
     * @since   1.0.0
     */
    public function getSearchDisplayedCharactersNumber(): int
    {
        if ($this->searchDisplayedCharactersNumberCallback !== null)
        {
            return \call_user_func($this->searchDisplayedCharactersNumberCallback);
        }
        else
        {
            return 200;
        }
    }

    /**
     * Getter for searchDisplayedCharactersNumberCallback function
     *
     * @return  callable  Function name or the actual function.
     *
     * @since   1.0.0
     */
    public function getSearchDisplayedCharactersNumberCallback(): callable
    {
        return $this->searchDisplayedCharactersNumberCallback;
    }

    /**
     * Setter for the searchDisplayedCharactersNumberCallback function.
     *
     * @param   callable  $function  Function name or the actual function.
     *
     * @return  callable  The previous function.
     *
     * @since   1.0.0
     */
    public function setSearchDisplayedCharactersNumberCallback(callable $function): callable
    {
        $previous = $this->searchDisplayedCharactersNumberCallback;
        $this->searchDisplayedCharactersNumberCallback = $function;

        return $previous;
    }

    /**
     * Loads a single language file and appends the results to the existing strings
     *
     * @param string   $extension  The extension for which a language file should be loaded.
     * @param string $basePath   The basepath to use.
     * @param string|null   $lang       The language to load, default null for the current language.
     * @param boolean $reload     Flag that will force a language to be reloaded if set to true.
     * @param boolean $default    Flag that force the default language to be loaded if the current does not exist.
     *
     * @return  boolean  True if the file has successfully loaded.
     *
     * @since   1.0.0
     */
    public function load(string $extension, string $basePath, ?string $lang = null, bool $reload = false, bool $default = true): bool
    {
        // If language is null set as the current language.
        if (!$lang)
        {
            $lang = $this->lang;
        }

        // Load the default language first if we're not debugging and a non-default language is requested to be loaded
        // with $default set to true
        if (!$this->debug && ($lang != $this->default) && $default)
        {
            $this->load($extension, $basePath, $this->default, false, true);
        }

        $path = LanguageHelper::getLanguagePath($basePath, $lang);

        $internal = $extension === 'system' || $extension == '';

        $filenames = array();

        if ($internal)
        {
            $filenames[] = "$path/system.ini";
            $filenames[] = "$path/$lang.ini";
        }
        else
        {
            // Try first without a language-prefixed filename.
            $filenames[] = "$path/$extension.ini";
            $filenames[] = "$path/$lang.$extension.ini";
        }

        foreach ($filenames as $filename)
        {
            if (isset($this->paths[$extension][$filename]) && !$reload)
            {
                // This file has already been tested for loading.
                $result = $this->paths[$extension][$filename];
            }
            else
            {
                // Load the language file
                $result = $this->loadLanguage($filename, $extension);
            }

            if ($result)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Loads a language file.
     *
     * This method will not note the successful loading of a file - use load() instead.
     *
     * @param string $fileName   The name of the file.
     * @param string $extension  The name of the extension.
     *
     * @return  boolean  True if new strings have been added to the language
     *
     * @see     Language::load()
     * @since   1.0.0
     */
    protected function loadLanguage(string $fileName, string $extension = 'unknown'): bool
    {
        $this->counter++;

        $result  = false;
        $strings = $this->parse($fileName);

        if ($strings !== array())
        {
            $this->strings = array_replace($this->strings, $strings, $this->override);
            $result = true;
        }

        // Record the result of loading the extension's file.
        if (!isset($this->paths[$extension]))
        {
            $this->paths[$extension] = array();
        }

        $this->paths[$extension][$fileName] = $result;

        return $result;
    }

    /**
     * Parses a language file.
     *
     * @param string $fileName  The name of the file.
     *
     * @return  array  The array of parsed strings.
     *
     * @since   1.0.0
     */
    protected function parse(string $fileName)
    {
        $strings = LanguageHelper::parseIniFile($fileName, $this->debug);

        // Debug the ini file if needed.
        if ($this->debug === true && is_file($fileName))
        {
            $this->debugFile($fileName);
        }

        return $strings;
    }

    /**
     * Debugs a language file
     *
     * @param string $filename  Absolute path to the file to debug
     *
     * @return  integer  A count of the number of parsing errors
     *
     * @throws  \InvalidArgumentException
     *@since   3.6.3
     */
    public function debugFile(string $filename): int
    {
        // Make sure our file actually exists
        if (!is_file($filename))
        {
            throw new \InvalidArgumentException(
                sprintf('Unable to locate file "%s" for debugging', $filename)
            );
        }

        // Initialise variables for manually parsing the file for common errors.
        $reservedWord = array('YES', 'NO', 'NULL', 'FALSE', 'ON', 'OFF', 'NONE', 'TRUE');
        $debug = $this->getDebug();
        $this->debug = false;
        $errors = array();
        $php_errormsg = null;

        // Open the file as a stream.
        $file = new \SplFileObject($filename);

        foreach ($file as $lineNumber => $line)
        {
            // Avoid BOM error as BOM is OK when using parse_ini.
            if ($lineNumber == 0)
            {
                $line = str_replace("\xEF\xBB\xBF", '', $line);
            }

            $line = trim($line);

            // Ignore comment lines.
            if (!\strlen($line) || $line[0] == ';')
            {
                continue;
            }

            // Ignore grouping tag lines, like: [group]
            if (preg_match('#^\[[^\]]*\](\s*;.*)?$#', $line))
            {
                continue;
            }

            // Remove any escaped double quotes \" from the equation
            $line = str_replace('\"', '', $line);

            $realNumber = $lineNumber + 1;

            // Check for odd number of double quotes.
            if (substr_count($line, '"') % 2 != 0)
            {
                $errors[] = $realNumber;
                continue;
            }

            // Check that the line passes the necessary format.
            if (!preg_match('#^[A-Z][A-Z0-9_:\*\-\.]*\s*=\s*".*"(\s*;.*)?$#', $line))
            {
                $errors[] = $realNumber;
                continue;
            }

            // Check that the key is not in the reserved constants list.
            $key = strtoupper(trim(substr($line, 0, strpos($line, '='))));

            if (\in_array($key, $reservedWord))
            {
                $errors[] = $realNumber;
            }
        }

        // Check if we encountered any errors.
        if (\count($errors))
        {
            $this->errorfiles[$filename] = $errors;
        }
        elseif ($php_errormsg)
        {
            // We didn't find any errors but there's probably a parse notice.
            $this->errorfiles['PHP' . $filename] = 'PHP parser errors :' . $php_errormsg;
        }

        $this->debug = $debug;

        return \count($errors);
    }

    /**
     * @throws LanguagePropertyException
     */
    public function __get($name)
    {
        $method = "get" . ucfirst($name);

        if(!isset($this->$name)) {
            throw new LanguagePropertyException(sprintf('Property %s can not be read from this Language', $name));
        }

        return $this->$method();
    }

    /**
     * Get a metadata language property.
     *
     * @param string $property  The name of the property.
     * @param   mixed   $default   The default value.
     *
     * @return  mixed  The value of the property.
     *
     * @since   1.0.0
     */
    public function get(string $property, $default = null)
    {
        if (isset($this->metadata[$property]))
        {
            return $this->metadata[$property];
        }

        return $default;
    }

    /**
     * Get a back trace.
     *
     * @return array
     *
     * @since 4.0.0
     */
    protected function getTrace(): array
    {
        return \function_exists('debug_backtrace') ? debug_backtrace() : [];
    }

    /**
     * Determine who called Language or Text.
     *
     * @return  array  Caller information.
     *
     * @since   1.0.0
     */
    protected function getCallerInfo(): array
    {
        // Try to determine the source if none was provided
        if (!\function_exists('debug_backtrace'))
        {
            return array();
        }

        $backtrace = debug_backtrace();
        $info = array();

        // Search through the backtrace to our caller
        $continue = true;

        while ($continue && next($backtrace))
        {
            $step = current($backtrace);
            $class = @ $step['class'];

            // We're looking for something outside of language.php
            if ($class != self::class && $class != Text::class)
            {
                $info['function'] = @ $step['function'];
                $info['class'] = $class;
                $info['step'] = prev($backtrace);

                // Determine the file and name of the file
                $info['file'] = @ $step['file'];
                $info['line'] = @ $step['line'];

                $continue = false;
            }
        }

        return $info;
    }

    /**
     * Getter for Name.
     *
     * @return  string  Official name element of the language.
     *
     * @since   1.0.0
     */
    public function getName(): string
    {
        return $this->metadata['name'];
    }

    /**
     * Get a list of language files that have been loaded.
     *
     * @param string|null $extension  An optional extension name.
     *
     * @return  array
     *
     * @since   1.0.0
     */
    public function getPaths(string $extension = null): array
    {
        if (isset($extension))
        {
            if (isset($this->paths[$extension]))
            {
                return $this->paths[$extension];
            }

            return [];
        }

        return $this->paths;
    }

    /**
     * Get a list of language files that are in error state.
     *
     * @return  array
     *
     * @since   1.0.0
     */
    public function getErrorFiles(): array
    {
        return $this->errorfiles;
    }

    /**
     * Getter for the language tag (as defined in RFC 3066)
     *
     * @return  string  The language tag.
     *
     * @since   1.0.0
     */
    public function getTag(): string
    {
        return $this->metadata['tag'];
    }

    /**
     * Getter for the calendar type
     *
     * @return  string  The calendar type.
     *
     * @since   3.7.0
     */
    public function getCalendar(): string
    {
        if (isset($this->metadata['calendar']))
        {
            return $this->metadata['calendar'];
        }
        else
        {
            return 'gregorian';
        }
    }

    /**
     * Get the RTL property.
     *
     * @return  boolean  True is it an RTL language.
     *
     * @since   1.0.0
     */
    public function isRtl(): bool
    {
        return (bool) $this->metadata['rtl'];
    }

    /**
     * Set the Debug property.
     *
     * @param boolean $debug  The debug setting.
     *
     * @return  boolean  Previous value.
     *
     * @since   1.0.0
     */
    public function setDebug(bool $debug): bool
    {
        $previous = $this->debug;
        $this->debug = (boolean) $debug;

        return $previous;
    }

    /**
     * Set the DebugKeys property.
     *
     * @param boolean $debugKeys  The debugKeys setting.
     *
     * @return  boolean  Previous value.
     *
     * @since   1.0.0
     */
    public function setDebugKeys(bool $debugKeys): bool
    {
        $previous = $this->debugKeys;
        $this->debugKeys = (boolean) $debugKeys;

        return $previous;
    }

    /**
     * Get the Debug property.
     *
     * @return  boolean  True is in debug mode.
     *
     * @since   1.0.0
     */
    public function getDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Get the Debug property.
     *
     * @return  boolean  True is in debug mode.
     *
     * @since   1.0.0
     */
    public function getDebugKeys(): bool
    {
        return $this->debugKeys;
    }

    /**
     * Get the default language code.
     *
     * @return  string  Language code.
     *
     * @since   1.0.0
     */
    public function getDefault(): string
    {
        return $this->default;
    }

    /**
     * Set the default language code.
     *
     * @param string $lang  The language code.
     *
     * @return  string  Previous value.
     *
     * @since   1.0.0
     */
    public function setDefault(string $lang): string
    {
        $previous = $this->default;
        $this->default = $lang;

        return $previous;
    }

    /**
     * Get the list of orphaned strings if being tracked.
     *
     * @return  array  Orphaned text.
     *
     * @since   1.0.0
     */
    public function getOrphans(): array
    {
        return $this->orphans;
    }

    /**
     * Get the list of used strings.
     *
     * Used strings are those strings requested and found either as a string or a constant.
     *
     * @return  array  Used strings.
     *
     * @since   1.0.0
     */
    public function getUsed(): array
    {
        return $this->used;
    }

    /**
     * Determines is a key exists.
     *
     * @param string $string  The key to check.
     *
     * @return  boolean  True, if the key exists.
     *
     * @since   1.0.0
     */
    public function hasKey(string $string): bool
    {
        $key = strtoupper($string);

        return isset($this->strings[$key]);
    }

    /**
     * Get the language locale based on current language.
     *
     * @return  array  The locale according to the language.
     *
     * @since   1.0.0
     */
    public function getLocale(): array
    {
        if (!isset($this->locale))
        {
            if (isset($this->metadata['locale']))
            {
                $this->locale = $this->metadata['locale'];
            }
            else
            {
                $this->locale = null;
            }
        }

        return $this->locale;
    }

    /**
     * Get the first day of the week for this language.
     *
     * @return  integer  The first day of the week according to the language
     *
     * @since   1.0.0
     */
    public function getFirstDay(): int
    {
        return (int) ($this->metadata['firstDay'] ?? 0);
    }

    /**
     * Get the weekends days for this language.
     *
     * @return  string  The weekend days of the week separated by a comma according to the language
     *
     * @since   1.0.0
     */
    public function getWeekEnd(): string
    {
        return $this->metadata['weekEnd'] ?? '0,6';
    }
}