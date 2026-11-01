{*
    Fragment returned to the core `loadable` XHR (?...&make=loadoptions).

    This is the WHOLE response: bare <option> tags, nothing around them. The
    controller renders only this file and stops, so anything extra would land
    inside the form's <select>. $loadlist is either the service codes or the
    template codes depending on the `which` parameter.
*}
{foreach from=$loadlist key=value item=label}
    <option value="{$value|escape}">{$label|escape}</option>
{foreachelse}
    <option value="">— Nhân Hòa không trả về mục nào —</option>
{/foreach}
