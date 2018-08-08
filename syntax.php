<?php
/**
 * DokuWiki Target-Link Plugin
 *
 * Make links with specified targets not depending on the default configuration.
 * e.g.: The links usual open in the same tab, but this link opens in a new tab.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Hokkaidoperson <dosankomali@yahoo.co.jp>
 *
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class syntax_plugin_targetlink extends DokuWiki_Syntax_Plugin {

    function getType(){
        return 'substition';
    }

    function getSort(){
        return 295; // between Doku_Parser_Mode_camelcaselink (290) and Doku_Parser_Mode_internallink (300)
    }

    function connectTo($mode) {
      $this->Lexer->addSpecialPattern('\[\[target=.*\|.*?\]\](?!\])',$mode,'plugin_targetlink');
      $this->Lexer->addSpecialPattern('\[\[\+tab\|.*?\]\](?!\])',$mode,'plugin_targetlink');
    }

    function handle($match, $state, $pos, Doku_Handler $handler) {

        return explode('|', substr($match, 2, -2));

    }

    function render($format, Doku_Renderer $renderer, $data) {
        if ($data[0] == '+tab') {
            $target = '_blank';
        } else {
            $target = substr($data[0], strlen('target='));
        }


        if($format == 'xhtml') {

            //decide which kind of link it is
            //(referred an idea from https://github.com/ironiemix/dokuwiki-plugin-menu/blob/master/syntax.php)
            $ref = $data[1];
            if ( preg_match('/^[a-zA-Z0-9\.]+>{1}.*$/u',$ref) ) {
                // Interwiki
                $interwiki = explode('>',$ref,2);
                $args = $this->interwikilink($renderer, $data[1], $data[2], $interwiki[0], $interwiki[1], $target);

            } elseif ( preg_match('/^\\\\\\\\[\w.:?\-;,]+?\\\\/u',$ref) ) {
                // Windows Share
                $args = $this->windowssharelink($renderer, $data[1], $data[2], $target);

            } elseif ( preg_match('#^([a-z0-9\-\.+]+?)://#i',$ref) ) {
                // external link (accepts all protocols)
                $args = $this->externallink($renderer, $data[1], $data[2], $target, $this->schemes);

            } else {
                // internal link
                $args = $this->internallink($renderer, $data[1], $data[2], $target);

            }

            $renderer->doc .= $renderer->_formatLink($args);

        }

        if($format == 'metadata') {
            //simply calls the default function

            //decide which kind of link it is
            //(referred an idea from https://github.com/ironiemix/dokuwiki-plugin-menu/blob/master/syntax.php)
            $ref = $data[1];
            if ( preg_match('/^[a-zA-Z0-9\.]+>{1}.*$/u',$ref) ) {
                // Interwiki
                $interwiki = explode('>',$ref,2);
                $renderer->interwikilink($data[1], $data[2], $interwiki[0], $interwiki[1]);

            } elseif ( preg_match('/^\\\\\\\\[\w.:?\-;,]+?\\\\/u',$ref) ) {
                // Windows Share
                $renderer->windowssharelink($data[1], $data[2]);

            } elseif ( preg_match('#^([a-z0-9\-\.+]+?)://#i',$ref) ) {
                // external link (accepts all protocols)
                $renderer->externallink($data[1], $data[2]);

            } else {
                // internal link
                $renderer->internallink($data[1], $data[2]);

            }

        }


    }

    // Got an idea from https://github.com/rpeyron/plugin-button/blob/master/syntax.php
    // and copied from original internallink/externallink/interwikilink/windowssharelink functions
    // added $target
    function internallink(&$xhtml, $id, $name = null, $target, $search = null, $returnonly = false, $linktype = 'content') {
        global $conf;
        global $ID;
        global $INFO;

        $params = '';
        $parts  = explode('?', $id, 2);
        if(count($parts) === 2) {
            $id     = $parts[0];
            $params = $parts[1];
        }

        // For empty $id we need to know the current $ID
        // We need this check because _simpleTitle needs
        // correct $id and resolve_pageid() use cleanID($id)
        // (some things could be lost)
        if($id === '') {
            $id = $ID;
        }

        // default name is based on $id as given
        $default = $xhtml->_simpleTitle($id);

        // now first resolve and clean up the $id
        resolve_pageid(getNS($ID), $id, $exists, $xhtml->date_at, true);

        $link = array();
        $name = $xhtml->_getLinkTitle($name, $default, $isImage, $id, $linktype);
        if(!$isImage) {
            if($exists) {
                $class = 'wikilink1';
            } else {
                $class       = 'wikilink2';
                $link['rel'] = 'nofollow';
            }
        } else {
            $class = 'media';
        }

        //keep hash anchor
        @list($id, $hash) = explode('#', $id, 2);
        if(!empty($hash)) $hash = $xhtml->_headerToLink($hash);

        //prepare for formating
        $link['target'] = $target;
        $link['style']  = '';
        $link['pre']    = '';
        $link['suf']    = '';
        // highlight link to current page
        if($id == $INFO['id']) {
            $link['pre'] = '<span class="curid">';
            $link['suf'] = '</span>';
        }
        $link['more']   = '';
        $link['class']  = $class;
        if($xhtml->date_at) {
            $params = $params.'&at='.rawurlencode($xhtml->date_at);
        }
        $link['url']    = wl($id, $params);
        $link['name']   = $name;
        $link['title']  = $id;
        //add search string
        if($search) {
            ($conf['userewrite']) ? $link['url'] .= '?' : $link['url'] .= '&amp;';
            if(is_array($search)) {
                $search = array_map('rawurlencode', $search);
                $link['url'] .= 's[]='.join('&amp;s[]=', $search);
            } else {
                $link['url'] .= 's='.rawurlencode($search);
            }
        }

        //keep hash
        if($hash) $link['url'] .= '#'.$hash;

        return $link;

        //output formatted
        //if($returnonly) {
        //    return $xhtml->_formatLink($link);
        //} else {
        //    $this->doc .= $this->_formatLink($link);
        //}
    }

    function externallink(&$xhtml, $url, $name = null, $target, $schemes, $returnonly = false) {
        global $conf;

        $name = $xhtml->_getLinkTitle($name, $url, $isImage);

        // url might be an attack vector, only allow registered protocols
        if(is_null($this->schemes)) $this->schemes = getSchemes();
        list($scheme) = explode('://', $url);
        $scheme = strtolower($scheme);
        if(!in_array($scheme, $this->schemes)) $url = '';

        // is there still an URL?
        if(!$url) {
            if($returnonly) {
                return $name;
            } else {
                $xhtml->doc .= $name;
            }
            return;
        }

        // set class
        if(!$isImage) {
            $class = 'urlextern';
        } else {
            $class = 'media';
        }

        //prepare for formating
        $link = array();
        $link['target'] = $target;
        $link['style']  = '';
        $link['pre']    = '';
        $link['suf']    = '';
        $link['more']   = '';
        $link['class']  = $class;
        $link['url']    = $url;
        $link['rel']    = '';

        $link['name']  = $name;
        $link['title'] = $xhtml->_xmlEntities($url);
        if($conf['relnofollow']) $link['rel'] .= ' nofollow';
        if($target) $link['rel'] .= ' noopener';

        return $link;

        //output formatted
        //if($returnonly) {
        //    return $xhtml->_formatLink($link);
        //} else {
        //    $this->doc .= $this->_formatLink($link);
        //}
    }

    function interwikilink(&$xhtml, $match, $name = null, $wikiName, $wikiUri, $target, $returnonly = false) {
        global $conf;

        $link           = array();
        $link['target'] = $target;
        $link['pre']    = '';
        $link['suf']    = '';
        $link['more']   = '';
        $link['name']   = $xhtml->_getLinkTitle($name, $wikiUri, $isImage);
        $link['rel']    = '';

        //get interwiki URL
        $exists = null;
        $url    = $xhtml->_resolveInterWiki($wikiName, $wikiUri, $exists);

        if(!$isImage) {
            $class         = preg_replace('/[^_\-a-z0-9]+/i', '_', $wikiName);
            $link['class'] = "interwiki iw_$class";
        } else {
            $link['class'] = 'media';
        }

        //do we stay at the same server? Use local target
        //if(strpos($url, DOKU_URL) === 0 OR strpos($url, DOKU_BASE) === 0) {
        //    $link['target'] = $conf['target']['wiki'];
        //}
        if($exists !== null && !$isImage) {
            if($exists) {
                $link['class'] .= ' wikilink1';
            } else {
                $link['class'] .= ' wikilink2';
                $link['rel'] .= ' nofollow';
            }
        }
        if($target) $link['rel'] .= ' noopener';

        $link['url']   = $url;
        $link['title'] = htmlspecialchars($link['url']);

        return $link;

        //output formatted
        //if($returnonly) {
        //    return $xhtml->_formatLink($link);
        //} else {
        //    $this->doc .= $this->_formatLink($link);
        //}
    }

    function windowssharelink(&$xhtml, $url, $name = null, $target, $returnonly = false) {
        global $conf;

        //simple setup
        $link = array();
        $link['target'] = $target;
        $link['pre']    = '';
        $link['suf']    = '';
        $link['style']  = '';

        $link['name'] = $xhtml->_getLinkTitle($name, $url, $isImage);
        if(!$isImage) {
            $link['class'] = 'windows';
        } else {
            $link['class'] = 'media';
        }

        $link['title'] = $xhtml->_xmlEntities($url);
        $url           = str_replace('\\', '/', $url);
        $url           = 'file:///'.$url;
        $link['url']   = $url;

        return $link;

        //output formatted
        //if($returnonly) {
        //    return $xhtml->_formatLink($link);
        //} else {
        //    $this->doc .= $this->_formatLink($link);
        //}
    }

}
