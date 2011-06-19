<?php

/*
 *  @author Martin Vseticka <vseticka.martin@gmail.com>
 * 
 *  @link http://bugs.php.net/bug.php?id=41053
 *  @link http://www.pankaj-k.net/weblog/2008/03/did_you_know_that_each_integer.html
 *  @link http://www.johnciacia.com/2011/02/01/array-vs-splfixedarray/
 *  @link http://im0rtality.com/2011/04/php-array-memory-usage-optimisation-trick/
 *  @link http://stackoverflow.com/questions/2049420/php-unpack-question
 *  @link http://www.mysqltutorial.org/mysql-stored-procedure-tutorial.aspx
 *  @link http://stackoverflow.com/questions/2092719/php-array-as-var-export-include-vs-unserialize-vs-json-endecode
 */

/**
 * Description of Nodes
 */
class TreeNodes implements Serializable {

    public $nodesTransitions = array();
    /** @var string binary packed list of integers */
    public $nodesParents = array();
    public $chars = "";
    public $results = array();
    public $emptyArray = array();
    public $nodesFailures = "";
    private $counter = 0;
    private $serializationType = "serialize";

    public function __construct() {
        
    }

    public function serialize() {

        if ($this->serializationType == 'phpFile') {
            $file = 'TreeNodes.serialization';
            $fh = fopen(dirname(__FILE__) . '/' . $file, 'w') or die("can't open file");
            fwrite($fh, "<?php\n");
            fwrite($fh, " \$this->nodesTransitios = " . var_export($this->nodesTransitions, true) . ';');
            fwrite($fh, " \$this->nodesParents = " . var_export($this->nodesParents, true) . ';');
            fwrite($fh, " \$this->chars = " . var_export($this->chars, true) . ';');
            fwrite($fh, " \$this->results = " . var_export($this->results, true) . ';');
            fwrite($fh, " \$this->nodesFailures = " . var_export($this->nodesFailures, true) . ';');
            fwrite($fh, " \$this->counter = " . var_export($this->counter, true) . ';');
            fclose($fh);

            return serialize($file);
        } elseif ($this->serializationType == 'serialize') {
            return serialize(
                    array($this->nodesTransitions,
                        $this->nodesParents,
                        $this->chars,
                        $this->results,
                        $this->nodesFailures,
                        $this->counter
                    )
            );
        } elseif ($this->serializationType == 'json') {
            return json_encode(
                    array($this->nodesTransitions,
                        $this->nodesParents,
                        $this->chars,
                        $this->results,
                        $this->nodesFailures,
                        $this->counter
                    )
            );
        }
    }

    public function unserialize($data) {

        if ($this->serializationType == 'phpFile') {
            include dirname(__FILE__) . '/' . unserialize($data);
        } elseif ($this->serializationType == 'serialize') {
            list($this->nodesTransitions,
                    $this->nodesParents,
                    $this->chars,
                    $this->results,
                    $this->nodesFailures,
                    $this->counter) = unserialize($data);
        } elseif ($this->serializationType == 'json') {
            list($this->nodesTransitions,
                    $this->nodesParents,
                    $this->chars,
                    $this->results,
                    $this->nodesFailures,
                    $this->counter) = json_decode($data, true);
        } elseif ($this->serializationType == 'array_storage') {
            $array_storage = new array_storage();
            list($this->nodesTransitions,
                    $this->nodesParents,
                    $this->chars,
                    $this->results,
                    $this->nodesFailures,
                    $this->counter) = $array_storage->Txt2Array(file_get_contents(dirname(__FILE__) . '/' . unserialize($data)));
        }
    }

    /**
     *
     * @param int $parentId
     * @param char $c
     * @return int 
     */
    public function addNode($parentId, $c) {

        $this->nodesTransitions[$this->counter] = array();
        $this->chars .= $c;
        $this->setFailure($this->counter, 0);
        $this->setParentId($this->counter, $parentId);

        return $this->counter++;
    }

    /// Adds pattern ending in this node
    /// <param name="result">Pattern</param>
    public function addResult($nodeId, $result) {
        if (isset($this->results[$nodeId])) {
            if (array_search($result, $this->results[$nodeId]) === false) {
                $this->results[$nodeId][] = $result;
            }
        } else {
            $this->results[$nodeId][] = $result;
        }
    }

    /// Adds pattern ending in this node
    /// <param name="result">Pattern</param>
    public function setResult($nodeId, $result) {
        $this->results[$nodeId] = $result;
    }

    public function getResult($nodeId) {
        if (isset($this->results[$nodeId])) {
            return $this->results[$nodeId];
        } else {
            return null;
        }
    }

    public function addAppendResults($nodeId, $results) {
        //if (array_search($result, $this->nodes[$nodeId][3]) === false) {
        //$this->nodes[$nodeId][3] = $result;
        $this->results[$nodeId] = array_merge($this->results[$nodeId], $results);
        //}
    }

    /// Adds transition node
    /// <param name="node">Node</param>
    public function addTransition($nodeId, $char, $targetNodeId) {
        $this->nodesTransitions[$nodeId][$char] = $targetNodeId;
        //$this->nodesTransitions[$nodeId] .= pack("N", $val);
    }

    /// Returns transition to specified character (if exists)    
    /// <param name="c">Character</param>
    /// <returns>Returns TreeNode or null</returns>
    public function getTransition($nodeId, $c, $omitCheck = 0) {
        return ($omitCheck === 1 || isset($this->nodesTransitions[$nodeId][$c])) ? $this->nodesTransitions[$nodeId][$c] : null;
    }

    public function getTransitions($nodeId) {
        return $this->nodesTransitions[$nodeId];
    }

    /// Returns true if node contains transition to specified character    
    /// <param name="c">Character</param>
    /// <returns>True if transition exists</returns>
    public function containsTransition($nodeId, $c) {
        return isset($this->nodesTransitions[$nodeId][$c]);
    }

    public function getFailure($nodeId) {
        $v = unpack("N*", substr($this->nodesFailures, $nodeId * 4, 4));
        return $v[1];
    }

    public function setFailure($nodeId, $val) {
        $this->nodesFailures .= pack("N", $val);
    }

    public function getChar($nodeId) {
        return mb_substr($this->chars, $nodeId, 1);
    }

    public function getParentId($nodeId) {
        $v = unpack("N*", substr($this->nodesParents, $nodeId * 4, 4));
        return $v[1];
    }

    public function setParentId($nodeId, $val) {
        $this->nodesParents .= pack("N", $val);
    }

    public function getResults($nodeId) {
        if (isset($this->results[$nodeId])) {
            return $this->results[$nodeId];
        } else {
            return $this->emptyArray;
        }
    }

}

