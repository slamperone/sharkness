{**
 * For the full copyright and license information, please view the
 * LICENSE.md file that was distributed with this source code.
 *}
{block name='copyright'}
  <div class="copyright">
    {block name='copyright_link'}
      <a href="{$urls.base_url}">
        {l s='%copyright% %year% - Sharkness' sprintf=['%copyright%' => '©', '%year%' => 'Y'|date] d='Shop.Theme.Global'}
      </a>
    {/block}
  </div>
{/block}
