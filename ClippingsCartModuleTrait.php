<?php

namespace Cissee\Webtrees\Module\ClippingsCart;

use Cissee\WebtreesExt\MoreI18N;
use Fisharebest\Webtrees\I18N;
use Vesta\CommonI18N;
use Vesta\ControlPanelUtils\Model\ControlPanelCheckbox;
use Vesta\ControlPanelUtils\Model\ControlPanelPreferences;
use Vesta\ControlPanelUtils\Model\ControlPanelSection;
use Vesta\ControlPanelUtils\Model\ControlPanelSubsection;
use Vesta\ModuleI18N;

trait ClippingsCartModuleTrait {

  protected function getMainTitle() {
    return CommonI18N::titleVestaClippingsCart();
  }

  public function getShortDescription() {
    $part2 = I18N::translate('Replacement for the original \'Clippings Cart\' module.');
    if (!$this->isEnabled()) {
      $part2 = ModuleI18N::translate($this, $part2);
    }
    return MoreI18N::xlate('Select records from your family tree and save them as a GEDCOM file.') . " " . $part2;
  }

  protected function getFullDescription() {
    $description = array();
    $description[] = $this->getShortDescription();

    $description[] =
            CommonI18N::requires1(CommonI18N::titleVestaCommon());

    return $description;
  }

  protected function createPrefs() {
    $generalSub = array();
    $generalSub[] = new ControlPanelSubsection(
            CommonI18N::displayedTitle(),
            array(
        /*new ControlPanelCheckbox(
                I18N::translate('Include the %1$s symbol in the module title', $this->getVestaSymbol()),
                null,
                'VESTA',
                '1'),*/
        new ControlPanelCheckbox(
                CommonI18N::vestaSymbolInClippingsCartTitle(),
                CommonI18N::vestaSymbolInTitle2(),
                'VESTA_MENU',
                '1')));

    $sections = array();
    $sections[] = new ControlPanelSection(
            CommonI18N::general(),
            null,
            $generalSub);

    return new ControlPanelPreferences($sections);
  }

}
