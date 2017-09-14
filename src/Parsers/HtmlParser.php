<?php
    namespace PSpider\Parsers;
    
    use DOMDocument;
    use DOMXPath;
    use PSpider\Parsers\Adapters\CssSelecter;

    class HtmlParser
    {
        static protected $_xpath;
        static protected $_document;
        
        protected $_contextnode = null;
                        
        static public function load($html)
        {
            if(is_file($html))
            {
                $html = file_get_contents($html);
            }
            
            self::$_document = new DOMDocument();  
            $flag = libxml_use_internal_errors(true);
            self::$_document->loadHTML(html_entity_decode($html,ENT_XML1));
            libxml_use_internal_errors($flag);
            
            self::$_xpath = new DOMXPath(self::$_document);
            self::$_xpath->registerNamespace("php", "http://php.net/xpath");
            self::$_xpath->registerPHPFunctions();
            
            return new self();
        }
        
        protected function __construct($contextnode = null) 
        {
            $this->_contextnode = $contextnode;
        }
        
        public function findText($expression,$contextnode=null)
        {
            $expression = CssSelecter::translate($expression);
            
            $text = [];
            
            $nodeList = self::$_xpath->query($expression,$contextnode);
            
            foreach($nodeList as $node)
            {
                $text[] = $node->textContent;
            }
            
            return $text;
        }
        
        public function find($expression,$contextnode=null)
        {
            $expression = CssSelecter::translate($expression);
            
            return $this->xpath($expression,$contextnode);
        }
        
        public function xpath($expression,$contextnode=null)
        {
            if(empty($contextnode) && !empty($this->_contextnode))
            {
                $contextnode = $this->_contextnode;
            }
            
            $nodeList = self::$_xpath->query($expression,$contextnode);
            
            $result = [];
  
            foreach($nodeList as $node)
            {
                $result[] = new self($node);
            }
            return $result;
        }
        
        public function remove($expression,$contextnode=null)
        {
            if(empty($contextnode) && !empty($this->_contextnode))
            {
                $contextnode = &$this->_contextnode;
            }
            else if(empty($contextnode))
            {
                $contextnode = &self::$_document;
            }

            $expression = CssSelecter::translate($expression); 

            $nodeList = self::$_xpath->query($expression,$contextnode);

            foreach($nodeList as $node)
            {
                $contextnode->removeChild($node);
            }
            
            return $this;
        }
        
        public function html($contextnode = null)
        {
            $contextnode = !empty($contextnode)?$contextnode:$this->_contextnode;
            
            return self::$_document->saveHtml($contextnode);
        }
        
        public function text($contextnode = null)
        {
            $contextnode = !empty($contextnode)?:$this->_contextnode;
            
            return $contextnode->textContent;
        }
        
    }