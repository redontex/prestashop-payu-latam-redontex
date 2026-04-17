{**
*  @author    Redontex SL 
*  @copyright 2026
*  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*}

<section id="{$moduleName}-displayAdminOrderMainBottom">
  <div class="card mt-2">
    <div class="card-header">
      <h3 class="card-header-title">
        <img src="{$moduleLogoSrc}" alt="{$moduleDisplayName}" width="20" height="20">
	        {$moduleDisplayName}
      </h3>
    </div>
    <div class="card-body">
      	<p>
	  		{l s='This order has been paid with %moduleDisplayName%.' mod='rdtx_payulatam' sprintf=['%moduleDisplayName%' => $moduleDisplayName]}
		</p>
    </div>
  </div>
</section>
