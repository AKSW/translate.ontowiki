<?php

/**
 * OntoWiki module – Translate
 *
 *
 * @category   OntoWiki
 * @package    extensions_modules_translate
 * @author     Michael Martin <martin@informatik.uni-leipzig.de>
 * @copyright  Copyright (c) 2010, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class TranslateModule extends OntoWiki_Module
{
    protected $session = null;
    protected $languages;
    protected $locale;

    protected $properties;
    protected $resource;
    protected $translationMode = null;
    protected $newTitles = array();
    protected $titles = array();

    public function init() {
        $this->session      = $this->_owApp->session;
        $this->languages    = $this->_privateConfig->languages;
        $this->properties    = $this->_privateConfig->properties;
        $this->locale       = $this->_owApp->config->languages->locale;
        $this->titleHelper  = new OntoWiki_Model_TitleHelper($this->_owApp->selectedModel);

        $this->view->locale       = $this->_owApp->config->languages->locale;
        $this->view->titleHelper = $this->titleHelper;
        $this->view->languages    = $this->languages;
        $this->view->properties = $this->properties;
        $this->resource = $this->view->resource = (string) $this->_owApp->selectedResource;

        $this->titles = $this->getExistingTitles();
        $this->titles = $this->checkMissingTitles($this->titles);

    	if (null === $this->_owApp->selectedModel) {
            return;
        }
        
        foreach ($this->languages as $key => $element) {
            if($element->default == TRUE) {
                $this->selectedBaseLanguage = $element->code;
            }
        }

        if (!empty($this->_request->translationMode)) {
            $this->translationMode = $this->_request->translationMode;

            if (!empty($this->_request->selectedBaseLanguage)) {
                $this->selectedBaseLanguage = $this->_request->selectedBaseLanguage;
            }
        }
        switch ($this->translationMode) {
            case 'auto' :
                $this->titles = $this->translateTitles($this->titles);

            if($this->_request->PersistencyConfig == "withSaving") {
                $this->saveTitles();
                $this->view->withoutSaving = false;
            } else {
                $this->view->withoutSaving = true;
            }
            break;
            default :
            break;
        }

        $this->view->titles = $this->titles;
        $this->view->selectedBaseLanguage = $this->selectedBaseLanguage;
    }

    public function getContents() {
        return $this->render('translate');
    }

    private function getExistingTitles() {
        $constraints = array();
        foreach ($this->properties as $property)  {
            $constraints[] = "?predicate = <" . $property . "> ";
        }
        $constraint = implode (" || " , $constraints);
        $query = " SELECT ?predicate ?object WHERE {
            <".$this->resource."> ?predicate ?object .
            FILTER (".$constraint.")
        }";
        $results = $this->_owApp->selectedModel->sparqlQuery($query, array('result_format' => "extended"));
        $translations = array();
        foreach ( $results['results']['bindings'] as $key => $result ) {
            $this->titleHelper->addResource($result['predicate']['value']);
            $translations[$result['predicate']['value']][$key]['title'] = $result['object']['value'];
            if (!empty($result['object']['xml:lang'])) {
                $translations[$result['predicate']['value']][$key]['lang'] = $result['object']['xml:lang'];
            }
            else {
                $translations[$result['predicate']['value']][$key]['lang'] = '';
            }
        }
        return $translations;
    }

    private function checkMissingTitles($translations) {
        $rdfLanguages = array();
        foreach ($translations as $property => $titles) {
            foreach ($this->languages as $key => $element) {
                $actualLanguage = $element->code;
                $hit = false;
                foreach($titles as $element) {
                    if ($element['lang'] == $actualLanguage) {
                        $hit = true;
                    }
                }
                if (!$hit) {
                    $translations[$property][] = array ('lang' =>  $actualLanguage);
                }
            }
        }
        return $translations;
    }

    private function translateTitles( $titleSet ) {
        require_once 'library/gtranslate-api-php/GTranslate.php';
        $gt = new Gtranslate;
        if ($this->_request->PropertySelection == "default") {
            $translateOnly = null;
        } else {
            $translateOnly = $this->_request->PropertySelection;
        }
        foreach ($titleSet as $property => $titles) {
            if (!$translateOnly || ($translateOnly == $property)) {
                $baseTitle = $this->getBaseTitle($titles);
                $baseLan = (!empty($baseTitle['lang'])) ? $baseTitle['lang'] : "";
                foreach ($titles as $key => $title) {
                    if (empty($title['title'])) {
                        $targetLan = (!empty($title['lang'])) ? $title['lang'] : "";
                        $callName = $baseLan . "_to_" . $targetLan;

                        try {
                            $translation = $gt->$callName($baseTitle['title']);
                             if($this->_request->PersistencyConfig == "withSaving") {
                                $message = "Translating <span style=\"font-style:italic;\"> " . $baseTitle['title'] . "</span> from <b>" . $this->languages->$baseLan->label . "</b> to <b>" .  $this->languages->$targetLan->label . "</b> .";
                                $this->_owApp->appendMessage(
                                    new OntoWiki_Message($message, OntoWiki_Message::SUCCESS, array('escape' => false))
                                );
                             }
                        } catch (GTranslateException $ge) {
                            $message = "Translating " . $baseTitle['title'] . " from " . $baseLan . " to " . $targetLan . " fails with code: . " . ((string) $ge);
                            $this->_owApp->appendMessage(
                                new OntoWiki_Message($message, OntoWiki_Message::ERROR, array('escape' => false))
                            );

                            $translation = null ;
                        }
                        $titleSet[$property][$key]['title'] = $translation ;

                        if ($translation != null) {
                            $this->newTitles[$this->resource][$property][] = array (
                                'type' => 'literal',
                                'value' => $translation,
                                'lang' => $targetLan
                            );
                        }
                    }
                }
            }
        }
        return $titleSet;
    }


    private function getBaseTitle($titles) {
        $baseTitle = "";
        foreach ($titles as $title) {
            if (!empty($title['title']) && !empty($title['lang'])) {
                if ($title['lang'] == $this->selectedBaseLanguage) {
                    $baseTitle = $title;
                }
            }
        }
        if (empty($baseTitle)) {
            foreach ($titles as $title) {
                if (!empty($title['title']) && !empty($title['lang'])) {
                    if ($title['lang'] == $this->locale) {
                        $baseTitle = $title;
                    }
                }
            }
        }
        if (empty($baseTitle)) {
            foreach ($titles as $title) {
                if (!empty($title['title']) && !empty($title['lang'])) {
                    $baseTitle = $title;
                }
            }
        }
        if (empty($baseTitle)) {
            foreach ($titles as $title) {
                if (!empty($title['title'])) {
                    $baseTitle = $title;
                }
            }
        }

        if (empty($baseTitle)) {
            $baseTitle['title'] = OntoWiki_Utils::getUriLocalPart($this->resource);
        }
        return $baseTitle;
    }

    private function saveTitles(  ) {
        if (!empty($this->newTitles)) {
            $this->_owApp->selectedModel->addMultipleStatements($this->newTitles);
        } else {
            $message = "No Translation needed. All property values are translated in all languages.";
            $this->_owApp->appendMessage(
                new OntoWiki_Message($message, OntoWiki_Message::INFO, array('escape' => false))
            );
        }
    }

    private function curPageURL() {
        $_SERVER["REQUEST_URI"] = str_replace("translationMode/auto","",$_SERVER["REQUEST_URI"]);
        $_SERVER["REQUEST_URI"] = str_replace("translationMode=auto","",$_SERVER["REQUEST_URI"]);
        $pageURL = 'http';
        if ($_SERVER["HTTPS"] == "on") {
            $pageURL .= "s";
        }
        $pageURL .= "://";
        if ($_SERVER["SERVER_PORT"] != "80") {
            $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
        } else {
            $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
        }
    return $pageURL;
    }


}


