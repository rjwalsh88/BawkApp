<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */

require_once('UIBase.php');
require_once('Link.php');

/**
 * Helper class for UITable. Every row in a UITable is ultimately converted to a UITableRow.
 * When constructing a UITable, a row can be a plain array for simplicity, or a UITableRow.
 * 
 * Constructing a UITableRow explicity allows you to add buttons and pictures, which are
 * formatted differently from normal cells.
 *
 * Another option is UITableRowForm for embedding forms in UITableRows.
 */
class UITableRow {
  private $cells;
  private $buttons;
  private $hasPicture = false;
  private $hideButtons = false;
  public function __construct($cells) {
    $this->cells = $cells;
    $this->buttons = array();
  }
  public function addCell($cell) {
    $this->cells[] = $cells;
    return $this;
  }
  public function addButton($button) {
    $this->buttons[] = $button;
    return $this;
  }
  
  /**
   * Call to indicate that the first cell is a picture, so it will be formatted correctly.
   */
  public function hasPicture($has) {
    $this->hasPicture = $has;
    return $this;
  }

  /**
   * Hides buttons attached to this row unless the row is being hovered over.
   */
  public function hideButtons() {
    $this->hideButtons = true;
    return $this;
  }
  public function numButtons() { return count($this->buttons); }

  public function html($colorclass, $nCols, $colons, $buttonsExist, $colwidths) {
    $reveal = ($this->hideButtons && $this->numButtons()) ? ' revealbutton' : '';
    $text = "\t\t\t<tr class=\"$colorclass$reveal\">\n";
    $row = $this->cells;
    if (count($row)==1) {
      $cell = $row[0];
      $text .= "\t\t\t\t<td colspan=$nCols>$cell</td>\n";
    } else {
      foreach ($row as $i => $cell) {
        $colwidth = idx($colwidths, $i, '');
        if (substr($cell, -1) == ':' && $colons) {
          $text .= "\t\t\t\t<td align=right valign=top$colwidth>$cell</td>\n";
        } else if ($i == 0 && $this->hasPicture) {
          $text .= "\t\t\t\t<td class=genimage$colwidth>$cell</td>\n";
        } else {
          $text .= "\t\t\t\t<td $colwidth>$cell</td>\n";
        }
      }
    }
    if (count($this->buttons) > 0) {
      $hide = ($this->hideButtons) ? 'hidebutton nofloat' : '';
      $text .= "\t\t\t\t<td><span class=\"$hide\">";
      foreach ($this->buttons as $button) {
        $text .= $button->icon();
      }
      $text .= "</span></td>\n";
    }	else if ($buttonsExist) { // buttons on other rows
      $text .= "<td>&nbsp;</td>";
    }
    $text .= "\t\t\t</tr>\n";
    return $text;
  }
}

/**
 * Helper class for UITable. Variant of UITableRow that contains a form to submit.
 * 
 * Constructing a UITableRowForm explicity allows you to add form parameters, behaviors,
 * and submit buttons.
 */
class UITableRowForm extends UITableRow {
  private static $id = 0;
  private static function nextID() {
    self::$sunetid++;
    return self::$sunetid;
  }
  private $method;
  private $action;
  private $name;
  
  /**
   * $cells : cells of this table row
   * $name : name of the form (arbitrary at this point)
   * $buttonText : text of the confirm button
   * $buttonIconName : icon name of the confirm button, defaults to the buttonText
   * $method : form method, default GET
   * $action : form action, default current page
   */
  public function __construct($cells, $name, $buttonText, $buttonIconName = null, $method = 'GET', $action = null, $confirmmessage = null) {
    parent::__construct($cells);
    if ($action == null) $action = Server::currentPageName();
    $this->method = $method;
    $this->action = $action;
    $this->name = $name . self::nextID();
    if ($confirmmessage) {
      $js = "doConfirm('" . addslashes(htmlspecialchars($confirmmessage)) 
            . "', document." . $this->name . ");";
    } else {
      $js = $this->name . ".submit();";
    }
    if (!$buttonIconName) $buttonIconName = $buttonText;

    $submitButton = UIButton::javascriptButton($buttonText, $js, $buttonIconName);
    $this->addButton($submitButton);
    require_js('validate');
    require_css('form');
  }
  public function hideButtons() { } // no-op for forms.
  
  public function html($colorclass, $nCols, $colons, $buttonsExist, $colwidths) {
    $text = "<form method=\"" . $this->method . "\" action=\""
      . $this->action . "\" name=\"" . $this->name . 
      "\" onSubmit=\"return validate(document." . $this->name . ")\" >";
    $text .= parent::html($colorclass, $nCols, $colons, $buttonsExist, $colwidths);
    return $text . "</form>";
  }
}

/**
 * Base class of UIDataTable and UIFieldTable, handles common construction tasks.
 */
abstract class UITable extends UIBase {
  private $stripes;
  private $header;
  private $entries;
  private $equal = false;
  private $center = false;
  private $equalExceptions = null;
  private $hideTableButtons = false;
      
  protected function __construct($entries, $header = null) {
    parent::__construct();
    $this->entries = $entries;
    $this->header = $header;
    require_css('table');
  }
  protected abstract function getTableClass();
  protected abstract function isStriped();
  protected function renderCellsToTop() { return true; }
  protected function rightJustifyColons() { return false; }
  protected function getHoverClass() { return ''; }
  
  public function hideTableButtons() {
    $this->hideTableButtons = true;
    return $this;
  }
  public function equalColumnWidths(array $exceptions = null) {
    $this->equal = true;
    $this->equalExceptions = $exceptions ? $exceptions : array();
    return $this;
  }
  public function centerCells() {
    $this->center = true;
    return $this;
  }
  protected function html() {
    $tableClass = $this->getTableClass() . $this->getHoverClass();
    $color2 = $this->isStriped() ? "striped" : "plain";
    $colons = $this->rightJustifyColons();
    $entries = $this->entries;
    $header = $this->header;
    $halign = $this->center ? ' align=center' : ' align=left';
    $firstRow = ($header) ? $header : $entries[0];
    $nCols = count($firstRow);
    $colwidth = '';
    $colwidths = array();
    if ($this->equal) {
      $percentage = (int)(100 / ($nCols - count($this->equalExceptions)));
      $colwidth = " width=\"$percentage%\"";
    }
    foreach ($firstRow as $i => $cell) {
      $thiscolwidth = $this->equal ?
        (in_array($i, $this->equalExceptions) ? '' : $colwidth) : '';
      $colwidths[] = $thiscolwidth;
    }
    $text = "\t\t<table class=\"$tableClass\" cellspacing=0>\n";
    $buttons = false;
    if ($header != null) {
      $text .= "\t\t\t<thead><tr>\n";
      foreach ($this->entries as $entry) {
        if (is_object($entry) && ($entry->numButtons() > 0)) {
          $buttons = true;
          break;
        }
      }
      foreach ($firstRow as $i => $cell) {
        $thiscolwidth = $colwidths[$i];
        $text .= "\t\t\t\t<th$halign$thiscolwidth>$cell</th>\n";
      }
      if ($buttons) {
        $actions = $this->hideTableButtons ? '' : 'Actions';
        $text .= "\t\t\t\t<th align=left$colwidth>$actions</th>\n";
      }
      $text .= "\t\t\t</tr></thead>";
    }
    $colorFlag = false;
    $valign = $this->renderCellsToTop() ? ' valign="top"' : ' valign="middle"';
    $text .= "\t\t\t<tbody$valign$halign>\n";      

    foreach ($entries as $entry) {
      $color = $colorFlag ? 'plain': $color2;
      $colorFlag = !$colorFlag;
      if (is_array($entry)) {
        $entry = new UITableRow($entry);
      }
      if ($this->hideTableButtons) $entry->hideButtons();
      $text .= $entry->html($color, $nCols, $colons, $buttons, $colwidths);
    }
    $text .= "\t\t</tbody></table>\n";
    return $text;
  }
}


/**
 * Table that is optimized for displaying pairings of field names and values.
 *
 * For example, works well for info tables about single objects.
 * Defaults to a white background with no hover effects.
 * By default, fields ending in colons in Column 1 are right-justified.
 */
class UIFieldTable extends UITable {
  public function __construct($entries, $header = null) {
    parent::__construct($entries, $header);
  }
  protected function getTableClass() { return 'fieldtable'; }
  protected function isStriped() { return false; }
  protected function rightJustifyColons() { return true; }
}

/**
 * Table that is optimized for displaying complex data in charts.
 *
 * For example, works well for info tables about multiple objects.
 * Defaults to a striped background with hover-row effects.
 */
class UIDataTable extends UITable {
  private $top = false;
  private $striped = true;
  private $hoverclass = ' hoverrows';
  public function __construct($entries, $header = null) {
    parent::__construct($entries, $header);
  }
  protected function getTableClass() { return 'datatable'; }
  protected function isStriped() { return $this->striped; }
  protected function renderCellsToTop() { return $this->top; }
  protected function getHoverClass() { return $this->hoverclass; }

  public function noHover() {
    $this->hoverclass = '';
    return $this;
  }
  public function hoverCells() {
    $this->hoverclass = ' hovercells';
    return $this;
  }

  public function setStriped($striped) {
    $this->striped = $striped;
    return $this;
  }
  public function alignToTop() {
    $this->top = true;
    return $this;
  }
}


?>