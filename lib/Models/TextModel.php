<?php

namespace Tritrics\Ahoi\v1\Models;

use \DOMDocument;
use \DOMElement;
use \DOMText;
use \DOMCdataSection;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\LogicException;
use Tritrics\Ahoi\v1\Data\BaseModel;
use Tritrics\Ahoi\v1\Helper\LinkHelper;

/**
 * Model for Kirby's fields: list, slug, text, textarea, writer
 * 
 * Possible Texttypes:
 * 
 * -------------------------------------------------------------------------------------------
 * | FIELD-TYPE   | FIELD-DEF              | FORMATTING | LINEBREAKS        | API-TYPE       | 
 * |--------------|------------------------|------------|-------------------|----------------|
 * | text, slug   |                        | ./.        | ./.               | string         |
 * |--------------|------------------------|------------|-------------------|----------------|
 * | textarea     | buttons: false         | ./.        | \n                | text           |
 * |--------------|------------------------|------------|-------------------|----------------|
 * | textarea     | buttons: true          | markdown   | \n                | markdown       |
 * |--------------|------------------------|------------|-------------------|----------------|
 * | textarea     | buttons: false|true    | html       | <blocks>          | html           |
 * |              | api: html: true        |            | <br>              |                |
 * |--------------|------------------------|------------|-------------------|----------------|
 * | writer, list | inline: false          | html       | <blocks>          | html           |
 * |              |                        |            | <br>              |                |
 * |--------------|------------------------|------------|-------------------|----------------|
 * | writer       | inline: true           | html       | <br>              | html           |
 * |              | or nor breaks in text  |            |                   | without elem   |
 * -------------------------------------------------------------------------------------------
 * 
 * Textarea parsing as html is provided for older Kirby-projects, where writer-field was
 * not existing. In new projects writer should be used for html and textarea for text/markdown.
 * The possible combination: $textarea->kirbytext()->inline() is not provided, because the
 * field-buttons contains the block-elements headline and lists. These blocks are
 * stripped out by inline() which only makes sense, when the buttons are configured without.
 */
class TextModel extends BaseModel
{
  /**
   */
  public function __construct()
  {
    parent::__construct(...func_get_args());
    $this->setData();
  }

  /**
   * Set model data.
   */
  private function setData(): void
  {
    // [html, text, markdown, string]
    switch ($this->blueprint->node('type')->get()) {
      case 'textarea':
        if ($this->blueprint->node('api', 'html')->is(true)) {
          $type = 'html';
        } elseif ($this->blueprint->node('buttons')->is(false)) {
          $type = 'text';
        } else {
          $type = 'markdown';
        }
        break;
      case 'list':
        $type = 'html';
        break;
      case 'writer':
        $type = 'html';
        break;
      default: // text, slug
        $type = 'string';
    }
    $this->add('type', $type);
    $this->add('value', $this->getValue($type));
  }

  /**
   * Helper to convert DOMDocument to array.
   * Credits to https://gist.github.com/yosko/6991691
   * 
   * @throws InvalidArgumentException 
   * @throws LogicException 
   */
  function htmlToArray(DOMElement|DOMText|DOMCdataSection $root): array
  {
    // node with nodetype
    if ($root->nodeType == XML_ELEMENT_NODE) {
      $res = ['elem' => strtolower($root->nodeName)];
      if ($root->hasChildNodes()) {
        $res['value'] = [];
        $children = $root->childNodes;

        // <li> has a <p> as child -> remove
        if ($res['elem'] === 'li' && $children->length === 1 && $children->item(0)->nodeName === 'p') {
          $child = $this->htmlToArray($children->item(0));
          $res['value'] = $child['value'];
        }
        
        // all other
        else {
          for ($i = 0; $i < $children->length; $i++) {
            $child = $this->htmlToArray($children->item($i));
            if (!empty($child)) {
              $res['value'][] = $child;
            }
          }
        }

        // if it's only a block-element with simple text, then remove children
        if (
          is_array($res['value']) &&
          count($res['value']) === 1 &&
          is_array($res['value'][0]) &&
          count($res['value'][0]) === 1 &&
          isset($res['value'][0]['value'])
        ) {
          $res['value'] = $res['value'][0]['value'];
        }
      }

      // add attributes as optional 3rd entry
      $attr = [];
      if ($root->hasAttributes()) {
        foreach ($root->attributes as $attribute) {
          $attr[$attribute->name] = $attribute->value;
        }
      }

      // change attributes, if it's a link
      if ($res['elem'] === 'a') {
        $meta = LinkHelper::get(
          $attr['href'] ?? null,
          $attr['title'] ??  null,
          (isset($attr['target']) && $attr['target'] === '_blank'),
          $this->lang
        );
        if (is_array($meta) && isset($meta['href'])) {
          $res['meta'] = $meta;
          $attr['href'] = $meta['href'];
        } else {

          // invalid link, so return text node
          $attr = [];
          $res = [
            'value' => $res['value']
          ];
        }
      }
      if (count($attr)) {
        $res['attr'] = $attr;
      }
      return $res;
    }

    // text node
    if ($root->nodeType == XML_TEXT_NODE || $root->nodeType == XML_CDATA_SECTION_NODE) {
      $value = $root->nodeValue;
      if (!empty($value)) {
        return [
          'value' => $value
        ];
      }
    }
  }

  /**
   * Get the value of model.
   * 
   * return value can be:
   * 
   * 1. A string for non-html fields
   * 
   * 2. A simple text
   * { text: 'the text' }
   * 
   * 3. A single block-element
   * { elem: 'h1', text: 'the text' }
   * 
   * 4. An array with more than one of the above where every
   * possible sub-element is in node children.
   * [ { elem: 'h1', text: 'the text' }, { elem: 'p', children: [] }]
   */
  private function getValue (string $type): string|array
  {
    if ($type !== 'html') {
      return (string) $this->model->value();
    }

    $fieldtype = $this->blueprint->node('type')->get();
    if ($fieldtype === 'textarea') {
      $buffer = $this->model->kirbytext();
    } else if ($fieldtype === 'writer') {
      $buffer = $this->model->text();
    } else if ($fieldtype === 'list') {
      $buffer = $this->model->list();
    } else { // error
      return '';
    }

    // delete line breaks
    $buffer = str_replace(["\n", "\r", "\rn"], "", $buffer);

    // delete special elements
    $buffer = str_replace(["<figure>", "</figure>"], "", $buffer);

    // make HTML editabel and get as array
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->loadHTML('
      <!DOCTYPE html>
      <html>
        <head>
          <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        </head>
        <body>' . $buffer . '</body>
      </html>'
    );
    $nodelist = $dom->getElementsByTagName('body');
    $res = $this->htmlToArray($nodelist->item(0));
    unset($res['elem']); // body

    if (isset($res['value'])) {
      if (is_array($res['value']) && count($res['value']) === 1) {
        $res = array_shift($res['value']);
      } else {
        $res = $res['value'];
      }
    }
    return $res;
  }
}