<?php

namespace Msgframework\Lib\Language;

use Joomla\Utilities\ArrayHelper;

class IniHelper
{
    /**
     * Default options array
     *
     * @var    array
     * @since  1.3.0
     */
    protected static array $options = array(
        'supportArrayValues' => false,
        'parseBooleanWords'  => false,
        'processSections'    => false,
    );

    /**
     * Convert of language variables to string for Ini file.
     *
     * @param $object
     * @param array $options
     *
     * @return string
     *
     * @since 1.0.0
     */

    public static function objectToString($object, array $options = []): string
    {
        $options            = array_merge(static::$options, $options);
        $supportArrayValues = $options['supportArrayValues'];

        $local  = [];
        $global = [];

        $variables = get_object_vars($object);

        $last = \count($variables);

        // Assume that the first element is in section
        $inSection = true;

        // Iterate over the object to set the properties.
        foreach ($variables as $key => $value)
        {
            // If the value is an object then we need to put it in a local section.
            if (\is_object($value))
            {
                // Add an empty line if previous string wasn't in a section
                if (!$inSection)
                {
                    $local[] = '';
                }

                // Add the section line.
                $local[] = '[' . $key . ']';

                // Add the properties for this section.
                foreach (get_object_vars($value) as $k => $v)
                {
                    if (\is_array($v) && $supportArrayValues)
                    {
                        $assoc = ArrayHelper::isAssociative($v);

                        foreach ($v as $arrayKey => $item)
                        {
                            $arrayKey = $assoc ? $arrayKey : '';
                            $local[]  = $k . '[' . $arrayKey . ']=' . self::getValueAsIni($item);
                        }
                    }
                    else
                    {
                        $local[] = $k . '=' . self::getValueAsIni($v);
                    }
                }

                // Add empty line after section if it is not the last one
                if (--$last !== 0)
                {
                    $local[] = '';
                }
            }
            elseif (\is_array($value) && $supportArrayValues)
            {
                $assoc = ArrayHelper::isAssociative($value);

                foreach ($value as $arrayKey => $item)
                {
                    $arrayKey = $assoc ? $arrayKey : '';
                    $global[] = $key . '[' . $arrayKey . ']=' . self::getValueAsIni($item);
                }
            }
            else
            {
                // Not in a section so add the property to the global array.
                $global[]  = $key . '=' . self::getValueAsIni($value);
                $inSection = false;
            }
        }

        return implode("\n", array_merge($global, $local));
    }
    /**
     * Method to get a value in an INI format.
     *
     * @param   mixed  $value  The value to convert to INI format.
     *
     * @return  string  The value in INI format.
     *
     * @since   1.0
     */
    protected static function getValueAsIni($value): string
    {
        $string = '';

        switch (\gettype($value))
        {
            case 'integer':
            case 'double':
                $string = $value;

                break;

            case 'boolean':
                $string = $value ? 'true' : 'false';

                break;

            case 'string':
                // Sanitize any CRLF characters..
                $string = '"' . str_replace(["\r\n", "\n"], '\\n', $value) . '"';

                break;
        }

        return $string;
    }
}