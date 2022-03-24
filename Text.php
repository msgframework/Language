<?php

namespace Msgframework\Lib\Language;

class Text
{
    /**
     * JavaScript strings
     *
     * @var    array
     * @since  1.0.0
     */
    protected static array $strings = array();

    /**
     * Translates a string into the current language.
     *
     * Examples:
     * `<script>alert(Text._('<?php echo Text::_("DEFAULT", false, false, true); ?>'));</script>`
     * will generate an alert message containing 'Default'
     * `<?php echo Text::_("DEFAULT"); ?>` will generate a 'Default' string
     *
     * @param string $string                The string to translate.
     * @param boolean    $jsSafe                Boolean: Make the result javascript safe.
     * @param boolean $interpretBackSlashes  To interpret backslashes (\\=\, \n=carriage return, \t=tabulation)
     * @param boolean $script                To indicate that the string will be push in the javascript language store
     *
     * @return  string  The translated string or the key if $script is true
     *
     * @since   1.0.0
     */
    public static function _(string $string, bool $jsSafe = false, bool $interpretBackSlashes = true, bool $script = false): string
    {
        if (self::passSprintf($string, $jsSafe, $interpretBackSlashes, $script))
        {
            return $string;
        }

        $lang = \Cms::getApplication()->getLanguage();

        if ($script)
        {
            static::$strings[$string] = $lang->_($string, $jsSafe, $interpretBackSlashes);

            return $string;
        }

        return $lang->_($string, $jsSafe, $interpretBackSlashes);
    }

    /**
     * Checks the string if it should be interpreted as sprintf and runs sprintf over it.
     *
     * @param string &$string               The string to translate.
     * @param boolean $jsSafe                Boolean: Make the result javascript safe.
     * @param boolean $interpretBackSlashes  To interpret backslashes (\\=\, \n=carriage return, \t=tabulation)
     * @param boolean $script                To indicate that the string will be push in the javascript language store
     *
     * @return  boolean  Whether the string be interpreted as sprintf
     *
     * @since   1.0.0
     */
    private static function passSprintf(string &$string, bool $jsSafe = false, bool $interpretBackSlashes = true, bool $script = false): bool
    {
        // Check if string contains a comma
        if (strpos($string, ',') === false)
        {
            return false;
        }

        $lang = \Cms::getApplication()->getLanguage();
        $string_parts = explode(',', $string);

        // Pass all parts through the Text translator
        foreach ($string_parts as $i => $str)
        {
            $string_parts[$i] = $lang->get($str, $jsSafe, $interpretBackSlashes);
        }

        $first_part = array_shift($string_parts);

        // Replace custom named placeholders with sprinftf style placeholders
        $first_part = preg_replace('/\[\[%([0-9]+):[^\]]*\]\]/', '%\1$s', $first_part);

        // Check if string contains sprintf placeholders
        if (!preg_match('/%([0-9]+\$)?s/', $first_part))
        {
            return false;
        }

        $final_string = vsprintf($first_part, $string_parts);

        // Return false if string hasn't changed
        if ($first_part === $final_string)
        {
            return false;
        }

        $string = $final_string;

        if ($script)
        {
            foreach ($string_parts as $i => $str)
            {
                static::$strings[$str] = $str;
            }
        }

        return true;
    }

    /**
     * Translates a string into the current language.
     *
     * Examples:
     * `<?php echo Text::alt('ALL', 'language'); ?>` will generate a 'All' string in English but a "Toutes" string in French
     * `<?php echo Text::alt('ALL', 'module'); ?>` will generate a 'All' string in English but a "Tous" string in French
     *
     * @param string $string                The string to translate.
     * @param string $alt                   The alternate option for global string
     * @param boolean $jsSafe                Boolean: Make the result javascript safe.
     * @param boolean $interpretBackSlashes  To interpret backslashes (\\=\, \n=carriage return, \t=tabulation)
     * @param boolean $script                To indicate that the string will be pushed in the javascript language store
     *
     * @return  string  The translated string or the key if $script is true
     *
     * @since   1.0.0
     */
    public static function alt(string $string, string $alt, bool $jsSafe = false, bool $interpretBackSlashes = true, bool $script = false): string
    {
        if (\Cms::getApplication()->getLanguage()->hasKey($string . '_' . $alt))
        {
            $string .= '_' . $alt;
        }

        return static::_($string, $jsSafe, $interpretBackSlashes, $script);
    }

    /**
     * Like Text::sprintf but tries to pluralise the string.
     *
     * Examples:
     * `<script>alert(Text._('<?php echo Text::plural("COM_PLUGINS_N_ITEMS_UNPUBLISHED", 1, array("script"=>true)); ?>'));</script>`
     * will generate an alert message containing '1 plugin successfully disabled'
     * `<?php echo Text::plural('COM_PLUGINS_N_ITEMS_UNPUBLISHED', 1); ?>` will generate a '1 plugin successfully disabled' string
     *
     * @param   string   $string  The format string.
     * @param   integer  $n       The number of items
     *
     * @return  string  The translated strings or the key if 'script' is true in the array of options
     *
     * @since   1.0.0
     */
    public static function plural($string, $n): string
    {
        $lang = \Cms::getApplication()->getLanguage();
        $args = func_get_args();
        $count = count($args);

        if ($count < 1)
        {
            return '';
        }

        if ($count == 1)
        {
            // Default to the normal sprintf handling.
            $args[0] = $lang->get($string);

            return call_user_func_array('sprintf', $args);
        }

        // Try the key from the language plural potential suffixes
        $found = false;
        $suffixes = $lang->getPluralSuffixes((int) $n);
        array_unshift($suffixes, (int) $n);

        foreach ($suffixes as $suffix)
        {
            $key = $string . '_' . $suffix;

            if ($lang->hasKey($key))
            {
                $found = true;
                break;
            }
        }

        if (!$found)
        {
            // Not found so revert to the original.
            $key = $string;
        }

        if (is_array($args[$count - 1]))
        {
            $args[0] = $lang->get(
                $key, array_key_exists('jsSafe', $args[$count - 1]) ? $args[$count - 1]['jsSafe'] : false,
                array_key_exists('interpretBackSlashes', $args[$count - 1]) ? $args[$count - 1]['interpretBackSlashes'] : true
            );

            if (array_key_exists('script', $args[$count - 1]) && $args[$count - 1]['script'])
            {
                static::$strings[$key] = call_user_func_array('sprintf', $args);

                return $key;
            }
        }
        else
        {
            $args[0] = $lang->get($key);
        }

        return call_user_func_array('sprintf', $args);
    }

    /**
     * Passes a string thru a sprintf.
     *
     * Note that this method can take a mixed number of arguments as for the sprintf function.
     *
     * The last argument can take an array of options:
     *
     * array('jsSafe'=>boolean, 'interpretBackSlashes'=>boolean, 'script'=>boolean)
     *
     * where:
     *
     * jsSafe is a boolean to generate a javascript safe strings.
     * interpretBackSlashes is a boolean to interpret backslashes \\->\, \n->new line, \t->tabulation.
     * script is a boolean to indicate that the string will be push in the javascript language store.
     *
     * @param string $string  The format string.
     *
     * @return  string  The translated strings or the key if 'script' is true in the array of options.
     *
     * @since   1.0.0
     */
    public static function sprintf(string $string): string
    {
        $lang = \Cms::getApplication()->getLanguage();
        $args = func_get_args();
        $count = count($args);

        if ($count < 1)
        {
            return '';
        }

        if (is_array($args[$count - 1]))
        {
            $args[0] = $lang->get(
                $string, array_key_exists('jsSafe', $args[$count - 1]) ? $args[$count - 1]['jsSafe'] : false,
                array_key_exists('interpretBackSlashes', $args[$count - 1]) ? $args[$count - 1]['interpretBackSlashes'] : true
            );

            if (array_key_exists('script', $args[$count - 1]) && $args[$count - 1]['script'])
            {
                static::$strings[$string] = call_user_func_array('sprintf', $args);

                return $string;
            }
        }
        else
        {
            $args[0] = $lang->get($string);
        }

        // Replace custom named placeholders with sprintf style placeholders
        $args[0] = preg_replace('/\[\[%([0-9]+):[^\]]*\]\]/', '%\1$s', $args[0]);

        return call_user_func_array('sprintf', $args);
    }

    /**
     * Passes a string thru an printf.
     *
     * Note that this method can take a mixed number of arguments as for the sprintf function.
     *
     * @param string $string  The format string.
     *
     * @return  mixed
     *
     * @since   1.7.0
     */
    public static function printf(string $string)
    {
        $lang = \Cms::getApplication()->getLanguage();
        $args = func_get_args();
        $count = count($args);

        if ($count < 1)
        {
            return '';
        }

        if (is_array($args[$count - 1]))
        {
            $args[0] = $lang->get(
                $string, array_key_exists('jsSafe', $args[$count - 1]) ? $args[$count - 1]['jsSafe'] : false,
                array_key_exists('interpretBackSlashes', $args[$count - 1]) ? $args[$count - 1]['interpretBackSlashes'] : true
            );
        }
        else
        {
            $args[0] = $lang->get($string);
        }

        return call_user_func_array('printf', $args);
    }

    /**
     * Get the strings that have been loaded to the JavaScript language store.
     *
     * @return  array
     *
     * @since   1.0.0
     */
    public static function getScriptStrings(): array
    {
        return static::$strings;
    }
}