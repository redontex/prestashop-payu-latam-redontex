{*
*  @author    Redontex SL 
*  @copyright 2026
*  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*}
<form method="post" action="{$gateway_url|escape:'htmlall':'UTF-8'}" id="webcheckout-form">
    {foreach from=$form_fields item=field}
        <input type="hidden" name="{$field.name|escape:'htmlall':'UTF-8'}" value="{$field.value|escape:'htmlall':'UTF-8'}" />
    {/foreach}
</form>

<script>
    document.getElementById('webcheckout-form').submit();
</script>
