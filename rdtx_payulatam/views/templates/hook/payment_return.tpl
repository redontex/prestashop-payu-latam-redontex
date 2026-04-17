{*
*  @author    Redontex SL 
*  @copyright 2026
*  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*}
<div class="box">

    <h3>{l s='Estado de tu pago con PayU' mod='rdtx_payulatam'}</h3>

    {if $order_status == $status_approved}
        <p class="alert alert-success">
            {l s='Tu pago ha sido aprobado, tu pedido está siendo preparado.  Gracias por tu preferencia.' mod='rdtx_payulatam'}
        </p>

    {elseif $order_status == $status_pending}
        <p class="alert alert-warning">
            {l s='Tu pago está pendiente de confirmación.' mod='rdtx_payulatam'}
        </p>

    {elseif $order_status == $status_failed}
        <p class="alert alert-danger">
            {l s='Tu pago ha presentado un fallo.' mod='rdtx_payulatam'}
        </p>

    {elseif $order_status == $status_failed}
        <p class="alert alert-info">
            {l s='Estamos procesando tu pago.' mod='rdtx_payulatam'}
        </p>
	{else}
        <p class="alert alert-danger">
            {l s='Tu pago fue rechazado.' mod='rdtx_payulatam'}
        </p>	
    {/if}

    <p>
        {l s='Referencia del pedido:' mod='rdtx_payulatam'}
        <strong>{$order_reference}</strong>
    </p>

</div>

