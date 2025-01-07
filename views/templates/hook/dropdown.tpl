{if $sync_message}
    <div class="alert alert-success">
        {$sync_message|escape:'html'}
    </div>
{/if}

<div class="dropdown">
    <button class="btn btn-primary dropdown-toggle" type="button" id="syncDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        Synchronise Products
    </button>
    <ul class="dropdown-menu" aria-labelledby="syncDropdown">
        <li>
            <a class="dropdown-item" href="{$sync_url}?action=syncToPrestaShop&redirect_url={$smarty.server.REQUEST_URI}">Sync product details to Prestashop</a>
        </li>
        <li>
            <a class="dropdown-item" href="{$sync_url}?action=syncToIkosoft&redirect_url={$smarty.server.REQUEST_URI}">Sync product details to Ikosoft</a>
        </li>
		<li>
            <a class="dropdown-item" href="{$sync_url}?action=syncQToPrestaShop&redirect_url={$smarty.server.REQUEST_URI}">Sync quantities to Prestashop</a>
        </li>
        <li>
            <a class="dropdown-item" href="{$sync_url}?action=syncQToIkosoft&redirect_url={$smarty.server.REQUEST_URI}">Sync quantities to Ikosoft</a>
        </li>
    </ul>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const alert = document.querySelector('.alert-success');
        if (alert) {
            setTimeout(() => {
                alert.remove();
            }, 5000); // 5 seconds
        }
    });
</script>

