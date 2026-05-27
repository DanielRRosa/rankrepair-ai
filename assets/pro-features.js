(function($){
  const root = $('.rankrepair-ai-pro-wrap');
  if (!root.length) return;
  const ajax = root.data('ajax');
  const nonce = root.data('nonce');
  function post(action, data, cb) {
    data = data || {};
    data.action = action;
    data.nonce = nonce;
    $.post(ajax, data).done(function(r){
      if (r && r.success) {
        if (cb) cb(r.data);
      } else {
        alert((r && r.data && r.data.message) || 'Request failed');
      }
    }).fail(function(x){ alert('Request failed: ' + x.status); });
  }
  $('#rr_export_backup').on('click', function(){ post('rankrepair_ai_export_backup',{post_type:$('#rr_backup_post_type').val(),limit:$('#rr_backup_limit').val()},function(d){$('#rr_backup_output').val(JSON.stringify(d.backup,null,2));}); });
  $('#rr_restore_snapshot').on('click', function(){ post('rankrepair_ai_restore_snapshot',{backup:$('#rr_restore_input').val(),restore_content:$('#rr_restore_content').is(':checked')?1:0,restore_seo:$('#rr_restore_seo').is(':checked')?1:0,restore_images:$('#rr_restore_images').is(':checked')?1:0},function(d){$('#rr_restore_status').text(d.message);}); });
  $('#rr_link_scan').on('click', function(){ post('rankrepair_ai_link_scan',{post_type:$('#rr_link_post_type').val(),limit:$('#rr_link_limit').val(),old_domain:$('#rr_old_domain').val()},function(d){renderRows('#rr_link_results',d.items,['id','title','issues']);}); });
  $('#rr_redirect_save').on('click', function(){ post('rankrepair_ai_redirect_save',{from:$('#rr_redirect_from').val(),to:$('#rr_redirect_to').val()},function(d){alert(d.message);}); });
  $('#rr_media_scan').on('click', function(){ post('rankrepair_ai_media_scan',{limit:$('#rr_media_limit').val(),old_domain:$('#rr_media_old_domain').val()},function(d){renderRows('#rr_media_results',d.items,['id','title','issues','alt']);}); });
  $('#rr_cost_estimate').on('click', function(){ post('rankrepair_ai_cost_estimate',{posts:$('#rr_est_posts').val(),chars:$('#rr_est_chars').val(),output:$('#rr_est_output').val()},function(d){$('#rr_cost_results').html('<strong>Total tokens:</strong> '+esc(d.total_tokens)+'<br><strong>Input:</strong> '+esc(d.input_tokens)+' · <strong>Output:</strong> '+esc(d.output_tokens)+'<br>'+esc(d.note));}); });
  $('#rr_save_advanced').on('click', function(){ post('rankrepair_ai_save_advanced',{multilingual_safe_mode:$('#rr_multilingual_safe').is(':checked')?1:0,protect_dynamic_builder_meta:$('#rr_protect_builder').is(':checked')?1:0,enable_activity_log:$('#rr_activity_log').is(':checked')?1:0,production_warning:$('#rr_production_warning').is(':checked')?1:0,max_log_items:$('#rr_max_log_items').val()},function(d){alert(d.message);}); });
  function renderRows(sel,items,cols){ items = items || []; let html='<div class="rr-results"><p><strong>'+esc(items.length)+' item(s) found.</strong></p><table class="widefat striped"><thead><tr>'+cols.map(function(c){return '<th>'+esc(c)+'</th>';}).join('')+'</tr></thead><tbody>'; items.forEach(function(it){ html+='<tr>'+cols.map(function(c){return '<td>'+fmt(it[c])+'</td>';}).join('')+'</tr>'; }); html+='</tbody></table></div>'; $(sel).html(html); }
  function fmt(v){ if(Array.isArray(v)) return v.map(function(x){return '<span class="rr-badge">'+esc(x)+'</span>';}).join(' '); return esc(v||''); }
  function esc(s){ return String(s).replace(/[&<>'"]/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[m];}); }
})(jQuery);
