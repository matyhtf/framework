<?php
/**
 * Website: http://sourceforge.net/projects/simplehtmldom/
 * Additional projects that may be used: http://sourceforge.net/projects/debugobject/
 * Acknowledge: Jose Solorzano (https://sourceforge.net/projects/php-html/)
 * Contributions by:
 *	 Yousuke Kumakura (Attribute filters)
 *	 Vadim Voituk (Negative indexes supports of "find" method)
 *	 Antcs (Constructor with automatically load contents either text or file/url)
 *
 * all affected sections have comments starting with "PaperG"
 *
 * Paperg - Added case insensitive testing of the value of the selector.
 * Paperg - Added tag_start for the starting index of tags - NOTE: This works but not accurately.
 *  This tag_start gets counted AFTER \r\n have been crushed out, and after the remove_noice calls so it will not reflect the REAL position of the tag in the source,
 *  it will almost always be smaller by some amount.
 *  We use this to determine how far into the file the tag in question is.  This "percentage will never be accurate as the $dom->size is the "real" number of bytes the dom was created from.
 *  but for most purposes, it's a really good estimation.
 * Paperg - Added the forceTagsClosed to the dom constructor.  Forcing tags closed is great for malformed html, but it CAN lead to parsing errors.
 * Allow the user to tell us how much they trust the html.
 * Paperg add the text and plaintext to the selectors for the find syntax.  plaintext implies text in the innertext of a node.  text implies that the tag is a text node.
 * This allows for us to find tags based on the text they contain.
 * Create find_ancestor_tag to see if a tag is - at any level - inside of another specific tag.
 * Paperg: added parse_charset so that we know about the character set of the source document.
 *  NOTE:  If the user's system has a routine called get_last_retrieve_url_contents_content_type availalbe, we will assume it's returning the content-type header from the
 *  last transfer or curl_exec, and we will parse that and use it in preference to any other method of charset detection.
 *
 * Found infinite loop in the case of broken html in restore_noise.  Rewrote to protect from that.
 * PaperG (John Schlick) Added get_display_size for "IMG" tags.
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @author S.C. Chen <me578022@gmail.com>
 * @author John Schlick
 * @author Rus Carroll
 * @version 1.5 ($Rev: 208 $)
 * @package PlaceLocalInclude
 * @subpackage simple_html_dom
 */

namespace SPF\DOM;

/**
 * DOM Tree
 * Paperg - in the find routine: allow us to specify that we want case insensitive testing of the value of the selector.
 * Paperg - change $size from protected to public so we can easily access it
 * Paperg - added ForceTagsClosed in the constructor which tells us whether we trust the html or not.  Default is to NOT trust it.
 * @package PlaceLocalInclude
 */
class Tree
{
    const HDOM_TYPE_ELEMENT = 1;
    const HDOM_TYPE_COMMENT = 2;
    const HDOM_TYPE_TEXT = 3;
    const HDOM_TYPE_ENDTAG = 4;
    const HDOM_TYPE_ROOT = 5;
    const HDOM_TYPE_UNKNOWN = 6;
    const HDOM_QUOTE_DOUBLE = 0;
    const HDOM_QUOTE_SINGLE = 1;
    const HDOM_QUOTE_NO = 3;
    const HDOM_INFO_BEGIN = 0;
    const HDOM_INFO_END = 1;
    const HDOM_INFO_QUOTE = 2;
    const HDOM_INFO_SPACE = 3;
    const HDOM_INFO_TEXT = 4;
    const HDOM_INFO_INNER = 5;
    const HDOM_INFO_OUTER = 6;
    const HDOM_INFO_ENDSPACE = 7;
    const DEFAULT_TARGET_CHARSET = 'UTF-8';
    const DEFAULT_BR_TEXT = "\r\n";
    const DEFAULT_SPAN_TEXT = " ";
    const MAX_FILE_SIZE = 10000000;

    /**
     * @var Node
     */
    public $root = null;
    public $nodes = array();
    public $callback = null;
    public $lowercase = false;
    // Used to keep track of how large the text was when we started.
    public $original_size;
    public $size;
    protected $pos;
    protected $doc;
    protected $char;
    protected $cursor;
    protected $parent;
    protected $noise = array();
    protected $token_blank = " \t\r\n";
    protected $token_equal = ' =/>';
    protected $token_slash = " />\r\n\t";
    protected $token_attr = ' >';
    // Note that this is referenced by a child node, and so it needs to be public for that node to see this information.
    public $_charset = '';
    public $_target_charset = '';
    protected $default_br_text = "";
    public $default_span_text = "";

    // use isset instead of in_array, performance boost about 30%...
    protected $self_closing_tags = array(
        'img'=>1,
        'br'=>1,
        'input'=>1,
        'meta'=>1,
        'link'=>1,
        'hr'=>1,
        'base'=>1,
        'embed'=>1,
        'spacer'=>1,
    );

    protected $block_tags = array(
        'root' => 1,
        'body' => 1,
        'form' => 1,
        'div' => 1,
        'span' => 1,
        'table' => 1,
        'video' => 1,
        'source' => 1,
        'code' => 1,
        'pre' => 1,
    );

    // Known sourceforge issue #2977341
    // B tags that are not closed cause us to return everything to the end of the document.
    protected $optional_closing_tags = array(
        'tr'=>array('tr'=>1, 'td'=>1, 'th'=>1),
        'th'=>array('th'=>1),
        'td'=>array('td'=>1),
        'li'=>array('li'=>1),
        'dt'=>array('dt'=>1, 'dd'=>1),
        'dd'=>array('dd'=>1, 'dt'=>1),
        'dl'=>array('dd'=>1, 'dt'=>1),
        'p'=>array('p'=>1),
        'nobr'=>array('nobr'=>1),
        'b'=>array('b'=>1),
        'option'=>array('option'=>1),
    );

    public function __construct($str=null, $lowercase=true, $forceTagsClosed=true, $target_charset=self::DEFAULT_TARGET_CHARSET, $stripRN=true, $defaultBRText=self::DEFAULT_BR_TEXT, $defaultSpanText=self::DEFAULT_SPAN_TEXT)
    {
        if ($str) {
            if (preg_match("/^http:\/\//i", $str) || is_file($str)) {
                $this->load_file($str);
            } else {
                $this->load($str, $lowercase, $stripRN, $defaultBRText, $defaultSpanText);
            }
        }
        // Forcing tags to be closed implies that we don't trust the html, but it can lead to parsing errors if we SHOULD trust the html.
        if (!$forceTagsClosed) {
            $this->optional_closing_array=array();
        }
        $this->_target_charset = $target_charset;
    }

    public function __destruct()
    {
        $this->clear();
    }

    // load html from string
    public function load($str, $lowercase=true, $stripRN=true, $defaultBRText= self::DEFAULT_BR_TEXT, $defaultSpanText=self::DEFAULT_SPAN_TEXT)
    {
        global $debug_object;

        // prepare
        $this->prepare($str, $lowercase, $stripRN, $defaultBRText, $defaultSpanText);
        // strip out cdata
        $this->remove_noise("'<!\[CDATA\[(.*?)\]\]>'is", true);
        // strip out comments
        $this->remove_noise("'<!--(.*?)-->'is");
        // Per sourceforge http://sourceforge.net/tracker/?func=detail&aid=2949097&group_id=218559&atid=1044037
        // Script tags removal now preceeds style tag removal.
        // strip out <script> tags
        $this->remove_noise("'<\s*script[^>]*[^/]>(.*?)<\s*/\s*script\s*>'is");
        $this->remove_noise("'<\s*script\s*>(.*?)<\s*/\s*script\s*>'is");
        // strip out <style> tags
        $this->remove_noise("'<\s*style[^>]*[^/]>(.*?)<\s*/\s*style\s*>'is");
        $this->remove_noise("'<\s*style\s*>(.*?)<\s*/\s*style\s*>'is");
        // strip out preformatted tags
        //$this->remove_noise("'<\s*(?:code)[^>]*>(.*?)<\s*/\s*(?:code)\s*>'is");
        // strip out server side scripts
        $this->remove_noise("'(<\?)(.*?)(\?>)'s", true);
        // strip smarty scripts
        $this->remove_noise("'(\{\w)(.*?)(\})'s", true);

        // parsing
        while ($this->parse());
        // end
        $this->root->_[self::HDOM_INFO_END] = $this->cursor;
        $this->parse_charset();

        // make load function chainable
        return $this;
    }

    // load html from file
    public function load_file()
    {
        $args = func_get_args();
        $this->load(call_user_func_array('file_get_contents', $args), true);
        // Throw an error if we can't properly load the dom.
        if (($error=error_get_last())!==null) {
            $this->clear();
            return false;
        }
    }

    // set callback function
    public function set_callback($function_name)
    {
        $this->callback = $function_name;
    }

    // remove callback function
    public function remove_callback()
    {
        $this->callback = null;
    }

    // save dom as string
    public function save($filepath='')
    {
        $ret = $this->root->innertext();
        if ($filepath!=='') {
            file_put_contents($filepath, $ret, LOCK_EX);
        }
        return $ret;
    }

    // find dom node by css selector
    // Paperg - allow us to specify that we want case insensitive testing of the value of the selector.
    /**
     * @param $selector
     * @param null $idx
     * @param bool $lowercase
     * @return array| Node
     */
    public function find($selector, $idx=null, $lowercase=false)
    {
        return $this->root->find($selector, $idx, $lowercase);
    }

    // clean up memory due to php5 circular references memory leak...
    public function clear()
    {
        foreach ($this->nodes as $n) {
            $n->clear();
            $n = null;
        }
        // This add next line is documented in the sourceforge repository. 2977248 as a fix for ongoing memory leaks that occur even with the use of clear.
        if (isset($this->children)) {
            foreach ($this->children as $n) {
                $n->clear();
                $n = null;
            }
        }
        if (isset($this->parent)) {
            $this->parent->clear();
            unset($this->parent);
        }
        if (isset($this->root)) {
            $this->root->clear();
            unset($this->root);
        }
        unset($this->doc);
        unset($this->noise);
    }

    public function dump($show_attr=true)
    {
        $this->root->dump($show_attr);
    }

    // prepare HTML data and init everything
    protected function prepare($str, $lowercase=true, $stripRN=true, $defaultBRText=self::DEFAULT_BR_TEXT, $defaultSpanText=self::DEFAULT_SPAN_TEXT)
    {
        $this->clear();

        // set the length of content before we do anything to it.
        $this->size = strlen($str);
        // Save the original size of the html that we got in.  It might be useful to someone.
        $this->original_size = $this->size;

        //before we save the string as the doc...  strip out the \r \n's if we are told to.
        if ($stripRN) {
            $str = str_replace("\r", " ", $str);
            $str = str_replace("\n", " ", $str);

            // set the length of content since we have changed it.
            $this->size = strlen($str);
        }

        $this->doc = $str;
        $this->pos = 0;
        $this->cursor = 1;
        $this->noise = array();
        $this->nodes = array();
        $this->lowercase = $lowercase;
        $this->default_br_text = $defaultBRText;
        $this->default_span_text = $defaultSpanText;
        $this->root = new Node($this);
        $this->root->tag = 'root';
        $this->root->_[self::HDOM_INFO_BEGIN] = -1;
        $this->root->nodetype = self::HDOM_TYPE_ROOT;
        $this->parent = $this->root;
        if ($this->size>0) {
            $this->char = $this->doc[0];
        }
    }

    // parse html content
    protected function parse()
    {
        if (($s = $this->copy_until_char('<'))==='') {
            return $this->read_tag();
        }

        // text
        $node = new Node($this);
        ++$this->cursor;
        $node->_[self::HDOM_INFO_TEXT] = $s;
        $this->link_nodes($node, false);
        return true;
    }

    // PAPERG - dkchou - added this to try to identify the character set of the page we have just parsed so we know better how to spit it out later.
    // NOTE:  IF you provide a routine called get_last_retrieve_url_contents_content_type which returns the CURLINFO_CONTENT_TYPE from the last curl_exec
    // (or the content_type header from the last transfer), we will parse THAT, and if a charset is specified, we will use it over any other mechanism.
    protected function parse_charset()
    {
        global $debug_object;

        $charset = null;

        if (function_exists('get_last_retrieve_url_contents_content_type')) {
            $contentTypeHeader = get_last_retrieve_url_contents_content_type();
            $success = preg_match('/charset=(.+)/', $contentTypeHeader, $matches);
            if ($success) {
                $charset = $matches[1];
                if (is_object($debug_object)) {
                    $debug_object->debug_log(2, 'header content-type found charset of: ' . $charset);
                }
            }
        }

        if (empty($charset)) {
            $el = $this->root->find('meta[http-equiv=Content-Type]', 0);
            if (!empty($el)) {
                $fullvalue = $el->content;
                if (is_object($debug_object)) {
                    $debug_object->debug_log(2, 'meta content-type tag found' . $fullvalue);
                }

                if (!empty($fullvalue)) {
                    $success = preg_match('/charset=(.+)/', $fullvalue, $matches);
                    if ($success) {
                        $charset = $matches[1];
                    } else {
                        // If there is a meta tag, and they don't specify the character set, research says that it's typically ISO-8859-1
                        if (is_object($debug_object)) {
                            $debug_object->debug_log(2, 'meta content-type tag couldn\'t be parsed. using iso-8859 default.');
                        }
                        $charset = 'ISO-8859-1';
                    }
                }
            }
        }

        // If we couldn't find a charset above, then lets try to detect one based on the text we got...
        if (empty($charset)) {
            // Use this in case mb_detect_charset isn't installed/loaded on this machine.
            $charset = false;
            if (function_exists('mb_detect_encoding')) {
                // Have php try to detect the encoding from the text given to us.
                $charset = mb_detect_encoding($this->root->plaintext . "ascii", $encoding_list = array( "UTF-8", "CP1252" ));
                if (is_object($debug_object)) {
                    $debug_object->debug_log(2, 'mb_detect found: ' . $charset);
                }
            }

            // and if this doesn't work...  then we need to just wrongheadedly assume it's UTF-8 so that we can move on - cause this will usually give us most of what we need...
            if ($charset === false) {
                if (is_object($debug_object)) {
                    $debug_object->debug_log(2, 'since mb_detect failed - using default of utf-8');
                }
                $charset = 'UTF-8';
            }
        }

        // Since CP1252 is a superset, if we get one of it's subsets, we want it instead.
        if ((strtolower($charset) == strtolower('ISO-8859-1')) || (strtolower($charset) == strtolower('Latin1')) || (strtolower($charset) == strtolower('Latin-1'))) {
            if (is_object($debug_object)) {
                $debug_object->debug_log(2, 'replacing ' . $charset . ' with CP1252 as its a superset');
            }
            $charset = 'CP1252';
        }

        if (is_object($debug_object)) {
            $debug_object->debug_log(1, 'EXIT - ' . $charset);
        }

        return $this->_charset = $charset;
    }

    // read tag info
    protected function read_tag()
    {
        if ($this->char!=='<') {
            $this->root->_[self::HDOM_INFO_END] = $this->cursor;
            return false;
        }
        $begin_tag_pos = $this->pos;
        $this->char = (++$this->pos<$this->size) ? $this->doc[$this->pos] : null; // next

        // end tag
        if ($this->char==='/') {
            $this->char = (++$this->pos<$this->size) ? $this->doc[$this->pos] : null; // next
            // This represents the change in the simple_html_dom trunk from revision 180 to 181.
            // $this->skip($this->token_blank_t);
            $this->skip($this->token_blank);
            $tag = $this->copy_until_char('>');

            // skip attributes in end tag
            if (($pos = strpos($tag, ' '))!==false) {
                $tag = substr($tag, 0, $pos);
            }

            $parent_lower = strtolower($this->parent->tag);
            $tag_lower = strtolower($tag);

            if ($parent_lower!==$tag_lower) {
                if (isset($this->optional_closing_tags[$parent_lower]) && isset($this->block_tags[$tag_lower])) {
                    $this->parent->_[self::HDOM_INFO_END] = 0;
                    $org_parent = $this->parent;

                    while (($this->parent->parent) && strtolower($this->parent->tag)!==$tag_lower) {
                        $this->parent = $this->parent->parent;
                    }

                    if (strtolower($this->parent->tag)!==$tag_lower) {
                        $this->parent = $org_parent; // restore origonal parent
                        if ($this->parent->parent) {
                            $this->parent = $this->parent->parent;
                        }
                        $this->parent->_[self::HDOM_INFO_END] = $this->cursor;
                        return $this->as_text_node($tag);
                    }
                } elseif (($this->parent->parent) && isset($this->block_tags[$tag_lower])) {
                    $this->parent->_[self::HDOM_INFO_END] = 0;
                    $org_parent = $this->parent;

                    while (($this->parent->parent) && strtolower($this->parent->tag)!==$tag_lower) {
                        $this->parent = $this->parent->parent;
                    }

                    if (strtolower($this->parent->tag)!==$tag_lower) {
                        $this->parent = $org_parent; // restore origonal parent
                        $this->parent->_[self::HDOM_INFO_END] = $this->cursor;
                        return $this->as_text_node($tag);
                    }
                } elseif (($this->parent->parent) && strtolower($this->parent->parent->tag)===$tag_lower) {
                    $this->parent->_[self::HDOM_INFO_END] = 0;
                    $this->parent = $this->parent->parent;
                } else {
                    return $this->as_text_node($tag);
                }
            }

            $this->parent->_[self::HDOM_INFO_END] = $this->cursor;
            if ($this->parent->parent) {
                $this->parent = $this->parent->parent;
            }

            $this->char = (++$this->pos<$this->size) ? $this->doc[$this->pos] : null; // next
            return true;
        }

        $node = new Node($this);
        $node->_[self::HDOM_INFO_BEGIN] = $this->cursor;
        ++$this->cursor;
        $tag = $this->copy_until($this->token_slash);
        $node->tag_start = $begin_tag_pos;

        // doctype, cdata & comments...
        if (isset($tag[0]) && $tag[0]==='!') {
            $node->_[self::HDOM_INFO_TEXT] = '<' . $tag . $this->copy_until_char('>');

            if (isset($tag[2]) && $tag[1]==='-' && $tag[2]==='-') {
                $node->nodetype = self::HDOM_TYPE_COMMENT;
                $node->tag = 'comment';
            } else {
                $node->nodetype = self::HDOM_TYPE_UNKNOWN;
                $node->tag = 'unknown';
            }
            if ($this->char==='>') {
                $node->_[self::HDOM_INFO_TEXT].='>';
            }
            $this->link_nodes($node, true);
            $this->char = (++$this->pos<$this->size) ? $this->doc[$this->pos] : null; // next
            return true;
        }

        // text
        if ($pos=strpos($tag, '<')!==false) {
            $tag = '<' . substr($tag, 0, -1);
            $node->_[self::HDOM_INFO_TEXT] = $tag;
            $this->link_nodes($node, false);
            $this->char = $this->doc[--$this->pos]; // prev
            return true;
        }

        if (!preg_match("/^[\w-:]+$/", $tag)) {
            $node->_[self::HDOM_INFO_TEXT] = '<' . $tag . $this->copy_until('<>');
            if ($this->char==='<') {
                $this->link_nodes($node, false);
                return true;
            }

            if ($this->char==='>') {
                $node->_[self::HDOM_INFO_TEXT].='>';
            }
            $this->link_nodes($node, false);
            $this->char = (++$this->pos<$this->size) ? $this->doc[$this->pos] : null; // next
            return true;
        }

        // begin tag
        $node->nodetype = self::HDOM_TYPE_ELEMENT;
        $tag_lower = strtolower($tag);
        $node->tag = ($this->lowercase) ? $tag_lower : $tag;

        // handle optional closing tags
        if (isset($this->optional_closing_tags[$tag_lower])) {
            while (isset($this->optional_closing_tags[$tag_lower][strtolower($this->parent->tag)])) {
                $this->parent->_[self::HDOM_INFO_END] = 0;
                $this->parent = $this->parent->parent;
            }
            $node->parent = $this->parent;
        }

        $guard = 0; // prevent infinity loop
        $space = array($this->copy_skip($this->token_blank), '', '');

        // attributes
        do {
            if ($this->char!==null && $space[0]==='') {
                break;
            }
            $name = $this->copy_until($this->token_equal);
            if ($guard===$this->pos) {
                $this->char = (++$this->pos<$this->size) ? $this->doc[$this->pos] : null; // next
                continue;
            }
            $guard = $this->pos;

            // handle endless '<'
            if ($this->pos>=$this->size-1 && $this->char!=='>') {
                $node->nodetype = self::HDOM_TYPE_TEXT;
                $node->_[self::HDOM_INFO_END] = 0;
                $node->_[self::HDOM_INFO_TEXT] = '<'.$tag . $space[0] . $name;
                $node->tag = 'text';
                $this->link_nodes($node, false);
                return true;
            }

            // handle mismatch '<'
            if ($this->doc[$this->pos-1]=='<') {
                $node->nodetype = self::HDOM_TYPE_TEXT;
                $node->tag = 'text';
                $node->attr = array();
                $node->_[self::HDOM_INFO_END] = 0;
                $node->_[self::HDOM_INFO_TEXT] = substr($this->doc, $begin_tag_pos, $this->pos-$begin_tag_pos-1);
                $this->pos -= 2;
                $this->char = (++$this->pos<$this->size) ? $this->doc[$this->pos] : null; // next
                $this->link_nodes($node, false);
                return true;
            }

            if ($name!=='/' && $name!=='') {
                $space[1] = $this->copy_skip($this->token_blank);
                $name = $this->restore_noise($name);
                if ($this->lowercase) {
                    $name = strtolower($name);
                }
                if ($this->char==='=') {
                    $this->char = (++$this->pos<$this->size) ? $this->doc[$this->pos] : null; // next
                    $this->parse_attr($node, $name, $space);
                } else {
                    //no value attr: nowrap, checked selected...
                    $node->_[self::HDOM_INFO_QUOTE][] = self::HDOM_QUOTE_NO;
                    $node->attr[$name] = true;
                    if ($this->char!='>') {
                        $this->char = $this->doc[--$this->pos];
                    } // prev
                }
                $node->_[self::HDOM_INFO_SPACE][] = $space;
                $space = array($this->copy_skip($this->token_blank), '', '');
            } else {
                break;
            }
        } while ($this->char!=='>' && $this->char!=='/');

        $this->link_nodes($node, true);
        $node->_[self::HDOM_INFO_ENDSPACE] = $space[0];

        // check self closing
        if ($this->copy_until_char_escape('>')==='/') {
            $node->_[self::HDOM_INFO_ENDSPACE] .= '/';
            $node->_[self::HDOM_INFO_END] = 0;
        } else {
            // reset parent
            if (!isset($this->self_closing_tags[strtolower($node->tag)])) {
                $this->parent = $node;
            }
        }
        $this->char = (++$this->pos<$this->size) ? $this->doc[$this->pos] : null; // next

        // If it's a BR tag, we need to set it's text to the default text.
        // This way when we see it in plaintext, we can generate formatting that the user wants.
        // since a br tag never has sub nodes, this works well.
        if ($node->tag == "br") {
            $node->_[self::HDOM_INFO_INNER] = $this->default_br_text;
        }

        return true;
    }

    // parse attributes
    protected function parse_attr($node, $name, &$space)
    {
        // Per sourceforge: http://sourceforge.net/tracker/?func=detail&aid=3061408&group_id=218559&atid=1044037
        // If the attribute is already defined inside a tag, only pay atetntion to the first one as opposed to the last one.
        if (isset($node->attr[$name])) {
            return;
        }

        $space[2] = $this->copy_skip($this->token_blank);
        switch ($this->char) {
            case '"':
                $node->_[self::HDOM_INFO_QUOTE][] = self::HDOM_QUOTE_DOUBLE;
                $this->char = (++$this->pos<$this->size) ? $this->doc[$this->pos] : null; // next
                $node->attr[$name] = $this->restore_noise($this->copy_until_char_escape('"'));
                $this->char = (++$this->pos<$this->size) ? $this->doc[$this->pos] : null; // next
                break;
            case '\'':
                $node->_[self::HDOM_INFO_QUOTE][] = self::HDOM_QUOTE_SINGLE;
                $this->char = (++$this->pos<$this->size) ? $this->doc[$this->pos] : null; // next
                $node->attr[$name] = $this->restore_noise($this->copy_until_char_escape('\''));
                $this->char = (++$this->pos<$this->size) ? $this->doc[$this->pos] : null; // next
                break;
            default:
                $node->_[self::HDOM_INFO_QUOTE][] = self::HDOM_QUOTE_NO;
                $node->attr[$name] = $this->restore_noise($this->copy_until($this->token_attr));
        }
        // PaperG: Attributes should not have \r or \n in them, that counts as html whitespace.
        $node->attr[$name] = str_replace("\r", "", $node->attr[$name]);
        $node->attr[$name] = str_replace("\n", "", $node->attr[$name]);
        // PaperG: If this is a "class" selector, lets get rid of the preceeding and trailing space since some people leave it in the multi class case.
        if ($name == "class") {
            $node->attr[$name] = trim($node->attr[$name]);
        }
    }

    // link node's parent
    protected function link_nodes(&$node, $is_child)
    {
        $node->parent = $this->parent;
        $this->parent->nodes[] = $node;
        if ($is_child) {
            $this->parent->children[] = $node;
        }
    }

    // as a text node
    protected function as_text_node($tag)
    {
        $node = new Node($this);
        ++$this->cursor;
        $node->_[self::HDOM_INFO_TEXT] = '</' . $tag . '>';
        $this->link_nodes($node, false);
        $this->char = (++$this->pos<$this->size) ? $this->doc[$this->pos] : null; // next
        return true;
    }

    protected function skip($chars)
    {
        $this->pos += strspn($this->doc, $chars, $this->pos);
        $this->char = ($this->pos<$this->size) ? $this->doc[$this->pos] : null; // next
    }

    protected function copy_skip($chars)
    {
        $pos = $this->pos;
        $len = strspn($this->doc, $chars, $pos);
        $this->pos += $len;
        $this->char = ($this->pos<$this->size) ? $this->doc[$this->pos] : null; // next
        if ($len===0) {
            return '';
        }
        return substr($this->doc, $pos, $len);
    }

    protected function copy_until($chars)
    {
        $pos = $this->pos;
        $len = strcspn($this->doc, $chars, $pos);
        $this->pos += $len;
        $this->char = ($this->pos<$this->size) ? $this->doc[$this->pos] : null; // next
        return substr($this->doc, $pos, $len);
    }

    protected function copy_until_char($char)
    {
        if ($this->char===null) {
            return '';
        }

        if (($pos = strpos($this->doc, $char, $this->pos))===false) {
            $ret = substr($this->doc, $this->pos, $this->size-$this->pos);
            $this->char = null;
            $this->pos = $this->size;
            return $ret;
        }

        if ($pos===$this->pos) {
            return '';
        }
        $pos_old = $this->pos;
        $this->char = $this->doc[$pos];
        $this->pos = $pos;
        return substr($this->doc, $pos_old, $pos-$pos_old);
    }

    protected function copy_until_char_escape($char)
    {
        if ($this->char===null) {
            return '';
        }

        $start = $this->pos;
        while (1) {
            if (($pos = strpos($this->doc, $char, $start))===false) {
                $ret = substr($this->doc, $this->pos, $this->size-$this->pos);
                $this->char = null;
                $this->pos = $this->size;
                return $ret;
            }

            if ($pos===$this->pos) {
                return '';
            }

            if ($this->doc[$pos-1]==='\\') {
                $start = $pos+1;
                continue;
            }

            $pos_old = $this->pos;
            $this->char = $this->doc[$pos];
            $this->pos = $pos;
            return substr($this->doc, $pos_old, $pos-$pos_old);
        }
    }

    // remove noise from html content
    // save the noise in the $this->noise array.
    protected function remove_noise($pattern, $remove_tag=false)
    {
        global $debug_object;
        if (is_object($debug_object)) {
            $debug_object->debug_log_entry(1);
        }

        $count = preg_match_all($pattern, $this->doc, $matches, PREG_SET_ORDER|PREG_OFFSET_CAPTURE);

        for ($i=$count-1; $i>-1; --$i) {
            $key = '___noise___'.sprintf('% 5d', count($this->noise)+1000);
            if (is_object($debug_object)) {
                $debug_object->debug_log(2, 'key is: ' . $key);
            }
            $idx = ($remove_tag) ? 0 : 1;
            $this->noise[$key] = $matches[$i][$idx][0];
            $this->doc = substr_replace($this->doc, $key, $matches[$i][$idx][1], strlen($matches[$i][$idx][0]));
        }

        // reset the length of content
        $this->size = strlen($this->doc);
        if ($this->size>0) {
            $this->char = $this->doc[0];
        }
    }

    // restore noise to html content
    public function restore_noise($text)
    {
        global $debug_object;
        if (is_object($debug_object)) {
            $debug_object->debug_log_entry(1);
        }

        while (($pos=strpos($text, '___noise___'))!==false) {
            // Sometimes there is a broken piece of markup, and we don't GET the pos+11 etc... token which indicates a problem outside of us...
            if (strlen($text) > $pos+15) {
                $key = '___noise___'.$text[$pos+11].$text[$pos+12].$text[$pos+13].$text[$pos+14].$text[$pos+15];
                if (is_object($debug_object)) {
                    $debug_object->debug_log(2, 'located key of: ' . $key);
                }

                if (isset($this->noise[$key])) {
                    $text = substr($text, 0, $pos).$this->noise[$key].substr($text, $pos+16);
                } else {
                    // do this to prevent an infinite loop.
                    $text = substr($text, 0, $pos).'UNDEFINED NOISE FOR KEY: '.$key . substr($text, $pos+16);
                }
            } else {
                // There is no valid key being given back to us... We must get rid of the ___noise___ or we will have a problem.
                $text = substr($text, 0, $pos).'NO NUMERIC NOISE KEY' . substr($text, $pos+11);
            }
        }
        return $text;
    }

    // Sometimes we NEED one of the noise elements.
    public function search_noise($text)
    {
        global $debug_object;
        if (is_object($debug_object)) {
            $debug_object->debug_log_entry(1);
        }

        foreach ($this->noise as $noiseElement) {
            if (strpos($noiseElement, $text) !== false) {
                return $noiseElement;
            }
        }
        return false;
    }

    public function __toString()
    {
        return $this->root->innertext();
    }

    public function __get($name)
    {
        switch ($name) {
            case 'outertext':
                return $this->root->innertext();
            case 'innertext':
                return $this->root->innertext();
            case 'plaintext':
                return $this->root->text();
            case 'charset':
                return $this->_charset;
            case 'target_charset':
                return $this->_target_charset;
        }
        return false;
    }

    // camel naming conventions
    public function childNodes($idx = -1)
    {
        return $this->root->childNodes($idx);
    }

    public function firstChild()
    {
        return $this->root->first_child();
    }

    public function lastChild()
    {
        return $this->root->last_child();
    }

    public function createElement($name, $value = null)
    {
        return self::buildFromString("<$name>$value</$name>")->first_child();
    }

    public function createTextNode($value)
    {
        return end(self::buildFromString($value)->nodes);
    }

    public function getElementById($id)
    {
        return $this->find("#$id", 0);
    }

    public function getElementsById($id, $idx = null)
    {
        return $this->find("#$id", $idx);
    }

    public function getElementByTagName($name)
    {
        return $this->find($name, 0);
    }

    public function getElementsByTagName($name, $idx = -1)
    {
        return $this->find($name, $idx);
    }

    public function loadFile()
    {
        $args = func_get_args();
        $this->load_file($args);
    }


    /**
     *      helper functions
     * -----------------------------------------------------------------------------
     * get html dom from file
     *  $maxlen is defined in the code as PHP_STREAM_COPY_ALL which is defined as -1.
     * @param $url
     * @param bool $use_include_path
     * @param null $context
     * @param $offset
     * @param $maxLen
     * @param bool $lowercase
     * @param bool $forceTagsClosed
     * @param $target_charset
     * @param bool $stripRN
     * @param $defaultBRText
     * @param $defaultSpanText
     * @return bool|Tree
     */
    public static function buildFromFile($url, $use_include_path = false, $context=null, $offset = -1, $maxLen=-1, $lowercase = true, $forceTagsClosed=true, $target_charset = self::DEFAULT_TARGET_CHARSET, $stripRN=true, $defaultBRText=self::DEFAULT_BR_TEXT, $defaultSpanText=self::DEFAULT_SPAN_TEXT)
    {
        // We DO force the tags to be terminated.
        $dom = new Tree(null, $lowercase, $forceTagsClosed, $target_charset, $stripRN, $defaultBRText, $defaultSpanText);
        // For sourceforge users: uncomment the next line and comment the retreive_url_contents line 2 lines down if it is not already done.
        $contents = file_get_contents($url, $use_include_path, $context, $offset);
        // Paperg - use our own mechanism for getting the contents as we want to control the timeout.
        //$contents = retrieve_url_contents($url);
        if (empty($contents) or strlen($contents) > self::MAX_FILE_SIZE) {
            return false;
        }
        // The second parameter can force the selectors to all be lowercase.
        $dom->load($contents, $lowercase, $stripRN);
        return $dom;
    }

    /**
     * get html dom from string
     * @param $str
     * @param bool $lowercase
     * @param bool $forceTagsClosed
     * @param $target_charset
     * @param bool $stripRN
     * @param $defaultBRText
     * @param $defaultSpanText
     * @return bool|Tree
     */
    public static function buildFromString($str, $lowercase=true, $forceTagsClosed=true, $target_charset = self::DEFAULT_TARGET_CHARSET, $stripRN=true, $defaultBRText=self::DEFAULT_BR_TEXT, $defaultSpanText=self::DEFAULT_SPAN_TEXT)
    {
        $dom = new Tree(null, $lowercase, $forceTagsClosed, $target_charset, $stripRN, $defaultBRText, $defaultSpanText);
        if (empty($str) or strlen($str) > self::MAX_FILE_SIZE) {
            $dom->clear();
            return false;
        }
        $dom->load($str, $lowercase, $stripRN);
        return $dom;
    }

    /**
     * dump html dom tree
     * @param Node $node
     * @param bool $show_attr
     * @param int $deep
     */
    public static function dumpTree(Node $node, $show_attr=true, $deep=0)
    {
        $node->dump($node);
    }

    /**
     * find and remove dom node by css selector
     * @param string $selector
     * @param string $idx
     * @param array $excludes
     * @return array
     */
    public function findAndRemove($selector, $idx = null, $excludes = array())
    {
        if (empty($excludes)) {
            return $this->find($selector, $idx);
        }

        foreach ($excludes as $exclude) {
            foreach ($this->find($exclude) as $node) {
                $node->outertext = '';
            }
        }
        $this->load($this->save());
        return $this->find($selector, $idx);
    }
}
