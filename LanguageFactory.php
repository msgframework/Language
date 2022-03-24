<?php
namespace Msgframework\Lib\Language;

/**
 * Default factory for creating language objects
 *
 * @since  1.0.0
 */
class LanguageFactory implements LanguageFactoryInterface
{
    /**
     * Link to active Aplication
     *
     * @var $app
     */
    protected $app;

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
	public function createLanguage($app, string $lang, bool $debug = false): Language
	{
        $this->app = $app;
		return new Language($this, $lang, $debug);
	}

    public function getApplication()
    {
        return $this->app;
    }
}
