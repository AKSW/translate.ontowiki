<?php

require_once 'OntoWiki/Controller/Component.php';
require_once 'OntoWiki/Toolbar.php';
require_once 'OntoWiki/Navigation.php';
require_once 'Erfurt/Sparql/Query2.php';

/**
 * Controller for OntoWiki Filter Module
 *
 * @category   OntoWiki
 * @package    OntoWiki_extensions_components_files
 * @author     Michael Martin
 * @copyright  Copyright (c) 2009, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

class ModeltranslateController extends OntoWiki_Controller_Component {

    public function init() {
        parent::init();
        $this->_owApp = OntoWiki::getInstance();
        $this->store = $this->_owApp->erfurt->getStore();
        $this->translate = $this->_owApp->translate;
        $this->ac = $this->_erfurt->getAc();
        $this->locale       = $this->_owApp->config->languages->locale;
        $this->titleHelper  = new OntoWiki_Model_TitleHelper($this->_owApp->selectedModel);
        $this->view->locale      = $this->locale;
        $this->view->titleHelper = $this->titleHelper;

        // disable tabs
        OntoWiki_Navigation::disableNavigation();

        //set title of main window ...
        $this->view->placeholder('main.window.title')->set($this->translate->_('Model Translation', $this->_config->languages->locale));
        
        // get selected Model
        $this->model = $this->_owApp->selectedModel;
        // check if no model selected
        if (empty($this->model)) {
            throw new OntoWiki_Exception('Missing parameter m (model) and no selected model in session!');
            exit;
        }
        // check Model Based Access Control
        if (!$this->ac->isModelAllowed('view', $this->model->getModelIri()) ) {
            throw new Erfurt_Ac_Exception('You are not allowed to read this model.');
        }
    }

    /**
     * @access private
     *
     */
    public function initAction() {
        $this->createConfigurationElements();


        ##Dummy Call

        $predicates = array(    "http://www.w3.org/2000/01/rdf-schema#label",
                                "http://www.w3.org/2000/01/rdf-schema#comment"
                            );
        $languages   = array(   "en",
                                "de"
                            );

        $resources = $this->receiveResourceUris($predicates, $languages);
        $resElements = array();
        foreach ($resources as $resource) {
            $elements = $this->receiveLiteralValuesForResource($resource,$predicates, $languages);
            $this->titleHelper->addResource($resource);

            $resElements[$resource] = $elements;
        }
        $this->view->resources = $resElements;
    }


    private function createConfigurationElements() {

        $subjectVar = new Erfurt_Sparql_Query2_Var('subject');
        $predicateVar = new Erfurt_Sparql_Query2_Var('predicate');
        $objectVar = new Erfurt_Sparql_Query2_Var('object');

        $query = new Erfurt_Sparql_Query2();
        $query->addProjectionVar($predicateVar)->setDistinct(true);

        $elements[] = new Erfurt_Sparql_Query2_Triple($subjectVar,$predicateVar,$objectVar);
        $elements[] = new Erfurt_Sparql_Query2_Filter(new Erfurt_Sparql_Query2_isLiteral($objectVar));

        $query->addElements($elements);

        $query->setLimit(50);

        $predicates = $this->model->sparqlQuery($query);

        $this->view->predicates = array();
        foreach ($predicates as $key => $predicate)
        {
            $this->titleHelper->addResource($predicate['predicate']);
            $this->view->predicates[] = $predicate['predicate'];
        }
    }







    private function receiveResourceUris($predicates= array(), $languages = array("en"), $limit = 20 , $offset = 0) {

        $pFilter = "";
        if (!empty($predicates)) {
            $pFilters = array();
            foreach ($predicates as $predicate) {
                $pFilters[] = " ?p = <".$predicate."> ";
            }
            $pFilter = "FILTER ( " . (implode(" || ", $pFilters)) . " )";
        }

        $optionals = array();
        $bounds = array();
        $optional = "";
        $bFilter = "";
        $oIndex = 0;

        $optionals[] = "
            OPTIONAL {
              ?s ?p ?o .
              FILTER (isLiteral(?o))
            }";
        $bounds[] = " bound(?o) ";

        foreach ($languages as $language) {
            $optionals[] = "
                OPTIONAL {
                  ?s ?p ?o".$oIndex." .
                  FILTER (isLiteral(?o".$oIndex."))
                  FILTER ( langMatches( lang(?o".$oIndex."), \"".$language."\" ) )
                }
            ";
            $bounds[] = " bound(?o".$oIndex.") ";
            $oIndex++;
        }
        $optional = implode(" \n ", $optionals);
        $bFilter    = " Filter ( " . implode(" || ", $bounds) . " )";;
        $b2Filter    = " Filter (!( " . implode(" && ", $bounds) . ") )";;

        $query = "
            SELECT DISTINCT ?s
            WHERE {
            " .$optional. "
            " .$pFilter. "
            " .$bFilter. "
            " .$b2Filter. "
            }
            LIMIT ". $limit ."
            OFFSET " . $offset . "
        ";
        #echo "<xmp>" . ($query) . "</xmp>";
        $result = $this->model->sparqlQuery($query);
        $resources = array();
        foreach ($result as $entry) {
            $resources[] = $entry['s'];
        }
        return ($resources);
    }

    private function receiveLiteralValuesForResource($resourceUri, $predicates = array(), $languages = array("en")) {

        $pFilter = $lFilter = "";
        if (!empty($predicates)) {
            $pFilters = array();
            foreach ($predicates as $predicate) {
                $pFilters[] = " ?p = <".$predicate."> ";
            }
            $pFilter = "FILTER ( " . (implode(" || ", $pFilters)) . " )";
        }

         $lFilters[] = " lang(?o) = \"\"  ";
        foreach ($languages as $language) {
            $lFilters[] = " lang(?o) = \"".$language."\"  ";
        }
        $lFilter = "FILTER ( " . (implode(" || ", $lFilters)) . " )";

        $query = "
            SELECT ?p ?o
            WHERE {
                <".$resourceUri."> ?p ?o .
                FILTER (isLiteral(?o))
                ".$pFilter."
                ".$lFilter."
            }

        ";
var_dump($query);
        $results = $this->model->sparqlQuery($query, array('result_format' => "extended"));
        $values = array();
        if (!empty($results['results']['bindings'])) { $i = 0;
            foreach ($results['results']['bindings'] as $entry) {
                $this->titleHelper->addResource($entry['p']);
                $i++;
                $values[$entry['p']['value']][$i]['value'] = $entry['o']['value'];
                $values[$entry['p']['value']][$i]['lang'] = $entry['o']['xml:lang'];
            }
        }
        return $values;
    }

}
