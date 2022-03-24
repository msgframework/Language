<?php
namespace Msgframework\Lib\Language;

/**
 * Interface defining a factory which can create language objects
 *
 * @since  1.0.0
 */
interface LanguageFactoryInterface
{
    /**
     * Method to get an instance of a language.
     *
     * @param $app
     * @param string $lang The language to use
     * @param boolean $debug The debug mode
     *
     * @return  Language
     *
     * @since   1.0.0
     */
	public function createLanguage($app, string $lang, bool $debug = false): Language;

    public function getApplication();
}
