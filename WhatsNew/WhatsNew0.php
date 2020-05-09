<?php

namespace Cissee\Webtrees\Module\ClippingsCart\WhatsNew;

use Cissee\WebtreesExt\WhatsNew\WhatsNewInterface;
use Fisharebest\Webtrees\I18N;

class WhatsNew0 implements WhatsNewInterface {
  
  public function getMessage(): string {
    return I18N::translate("Vesta Clippings Cart: A new custom module. May be used to add shared places to the clippings cart. Otherwise same functionality as original clippings cart.");
  }
}
