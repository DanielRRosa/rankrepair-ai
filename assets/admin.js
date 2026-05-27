(function($){
  let rows = [];
  let previews = [];
  let aiItems = [];
  const ajax = (action, data={}) => $.post(RANKREPAIR_AI.ajax, {action, nonce: RANKREPAIR_AI.nonce, ...data});
  const esc = (v) => $('<div>').text(v || '').html();
  const ids = () => $('.rankrepair-ai-check:checked').map((_,el)=>$(el).val()).get();
  const status = (msg) => $('#rankrepair_ai_status').html(`<p>${esc(msg)}</p>`);
  const errorMsg = (xhr, fallback) => (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : fallback;
  const updateSelectionCount = () => $('#rankrepair_ai_selection_count').text(`${ids().length} selected`);

  function updateProviderFields(){
    const provider = $('#rankrepair_ai_ai_provider').val() || 'openai';
    $('.rankrepair-ai-provider-field').hide();
    $('.rankrepair-ai-provider-' + provider).show();
  }
  $(document).on('change', '#rankrepair_ai_ai_provider', updateProviderFields);
  function updateSeoPluginFields(){
    $('.rankrepair-ai-custom-meta-field').toggle(($('#rankrepair_ai_seo_plugin').val() || 'auto') === 'custom');
  }
  $(document).on('change', '#rankrepair_ai_seo_plugin', updateSeoPluginFields);
  $(function(){ updateProviderFields(); updateSeoPluginFields(); });

  const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));
  const chunk = (arr, size) => {
    const out = [];
    for(let i=0; i<arr.length; i+=size) out.push(arr.slice(i, i+size));
    return out;
  };

  function render(items){
    rows = items || [];
    const $tb = $('#rankrepair_ai_table tbody').empty();
    if(!rows.length){ $tb.append('<tr><td colspan="7">No posts found.</td></tr>'); updateSelectionCount(); return; }
    rows.forEach(r => {
      const issues = r.issues && r.issues.length ? r.issues.map(i=>`<span class="rankrepair-ai-badge">${esc(i)}</span>`).join(' ') : '<span class="rankrepair-ai-ok">No suspicious issue</span>';
      $tb.append(`<tr data-id="${r.id}">
        <td class="rankrepair-ai-col-check"><input type="checkbox" class="rankrepair-ai-check" value="${r.id}"></td>
        <td class="rankrepair-ai-post-id"><code>#${r.id}</code></td>
        <td class="rankrepair-ai-post-cell"><strong>${esc(r.post_title)}</strong><br><small>${esc(r.post_type)} · ${esc(r.status)} · ${esc(r.seo_plugin || 'SEO plugin')} · <a href="${esc(r.edit_link)}" target="_blank">Edit post</a></small></td>
        <td class="rankrepair-ai-issue-cell">${issues}</td>
        <td class="rankrepair-ai-current-cell"><details open><summary>Current SEO fields</summary><span class="rankrepair-ai-current-field"><b>Title:</b> ${esc(r.seo_title) || '<em>Empty</em>'}</span><span class="rankrepair-ai-current-field"><b>Description:</b> ${esc(r.meta_description) || '<em>Empty</em>'}</span><span class="rankrepair-ai-current-field"><b>Keyword:</b> ${esc(r.focus_keyword) || '<em>Empty</em>'}</span></details></td>
        <td class="rankrepair-ai-preview"></td>
        <td class="rankrepair-ai-row-actions"><button class="button button-primary rankrepair-ai-quick-ai-inline" data-post-id="${r.id}">Improve with AI</button><button class="button rankrepair-ai-rollback" data-id="${r.id}" ${r.has_backup ? '' : 'disabled'}>Rollback</button></td>
      </tr>`);
    });
    updateSelectionCount();
  }

  function ruleParams(){
    const action = $('#rankrepair_ai_action').val();
    const a = $('#rankrepair_ai_find').val();
    const b = $('#rankrepair_ai_replace').val();
    if(action === 'replace_text') return {find:a, replace:b};
    if(action === 'remove_text') return {text:a || b};
    if(action === 'add_prefix') return {prefix:b || a};
    if(action === 'add_suffix') return {suffix:b || a};
    if(action === 'set_pattern') return {pattern:b || a || '{post_title} | {brand_name}'};
    if(action === 'limit_chars') return {limit:b || a || '60'};
    return {};
  }

  $('#rankrepair_ai_save_settings').on('click', function(){
    $('#rankrepair_ai_settings_status').text('Saving...');
    ajax('rankrepair_ai_save_settings', {
      old_domain: $('#rankrepair_ai_old_domain').val(),
      new_domain: $('#rankrepair_ai_new_domain').val(),
      brand_name: $('#rankrepair_ai_brand_name').val(),
      language: $('#rankrepair_ai_language').val(),
      seo_plugin: $('#rankrepair_ai_seo_plugin').val(),
      custom_title_key: $('#rankrepair_ai_custom_title_key').val(),
      custom_description_key: $('#rankrepair_ai_custom_description_key').val(),
      custom_keyword_key: $('#rankrepair_ai_custom_keyword_key').val(),
      ai_provider: $('#rankrepair_ai_ai_provider').val(),
      openai_api_key: $('#rankrepair_ai_openai_api_key').val(),
      openai_model: $('#rankrepair_ai_openai_model').val(),
      anthropic_api_key: $('#rankrepair_ai_anthropic_api_key').val(),
      anthropic_model: $('#rankrepair_ai_anthropic_model').val(),
      gemini_api_key: $('#rankrepair_ai_gemini_api_key').val(),
      gemini_model: $('#rankrepair_ai_gemini_model').val(),
      ai_batch_size: $('#rankrepair_ai_ai_batch_size').val(),
      ai_throttle_mode: 'auto',
      max_content_chars: $('#rankrepair_ai_max_content_chars').val(),
      brand_voice: $('#rankrepair_ai_brand_voice').val(),
      default_content_status: $('#rankrepair_ai_content_status').val(),
      refresh_yoast_after_update: $('#rankrepair_ai_refresh_yoast_after_update').is(':checked') ? 1 : 0,
      safe_compatibility_mode: $('#rankrepair_ai_safe_compatibility_mode').is(':checked') ? 1 : 0,
      show_editor_box: $('#rankrepair_ai_show_editor_box').is(':checked') ? 1 : 0,
      show_list_table_tools: $('#rankrepair_ai_show_list_table_tools').is(':checked') ? 1 : 0,
      disable_builder_content_overwrite: $('#rankrepair_ai_disable_builder_content_overwrite').is(':checked') ? 1 : 0,
      render_schema_frontend: $('#rankrepair_ai_render_schema_frontend').is(':checked') ? 1 : 0,
      enabled_post_types: $('.rankrepair_ai_enabled_post_type:checked').map((_,el)=>$(el).val()).get()
    }).done(r => { $('#rankrepair_ai_settings_status').text(r.data.message || 'Saved.'); if($('.rankrepair-ai-launch').length){ window.location.href = 'admin.php?page=rankrepair-ai'; } }).fail(xhr => $('#rankrepair_ai_settings_status').text(errorMsg(xhr, 'Could not save.')));
  });



  $('#rr_link_scan').on('click', function(){
    const $out = $('#rr_link_results').html('Scanning links...');
    ajax('rankrepair_ai_link_scan', {
      post_type: $('#rr_link_post_type').val() || 'any',
      limit: $('#rr_link_limit').val() || 250,
      old_domain: $('#rr_old_domain').val() || ''
    }).done(r => {
      const items = (r.data && r.data.items) || [];
      if(!items.length){ $out.html('<p>No link issues found in this scan.</p>'); return; }
      $out.html('<table class="widefat striped"><thead><tr><th>Post</th><th>Issues</th></tr></thead><tbody>'+items.map(it => `<tr><td><a href="${esc(it.edit)}" target="_blank">${esc(it.title)} (#${it.id})</a></td><td>${(it.issues||[]).map(x=>esc(x.label||x)).join('<br>')}</td></tr>`).join('')+'</tbody></table>');
    }).fail(xhr => $out.text(errorMsg(xhr, 'Link scan failed.')));
  });

  $('#rr_media_scan').on('click', function(){
    const $out = $('#rr_media_results').html('Scanning media...');
    ajax('rankrepair_ai_media_scan', {
      limit: $('#rr_media_limit').val() || 300,
      old_domain: $('#rr_media_old_domain').val() || ''
    }).done(r => {
      const items = (r.data && r.data.items) || [];
      if(!items.length){ $out.html('<p>No media issues found in this scan.</p>'); return; }
      $out.html('<table class="widefat striped"><thead><tr><th>Media</th><th>Issues</th></tr></thead><tbody>'+items.map(it => `<tr><td>${it.thumb ? `<img src="${esc(it.thumb)}" style="width:46px;height:46px;object-fit:cover;margin-right:8px;vertical-align:middle;">` : ''}<a href="${esc(it.edit)}" target="_blank">${esc(it.title || 'Attachment')} (#${it.id})</a></td><td>${(it.issues||[]).map(x=>esc(x.label||x)).join('<br>')}</td></tr>`).join('')+'</tbody></table>');
    }).fail(xhr => $out.text(errorMsg(xhr, 'Media scan failed.')));
  });

  $('#rankrepair_ai_scan').on('click', function(){
    status('Scanning...');
    ajax('rankrepair_ai_scan', {
      post_type: $('#rankrepair_ai_post_type').val(),
      post_status: $('#rankrepair_ai_post_status').val(),
      issue_filter: $('#rankrepair_ai_issue_filter').val(),
      search: $('#rankrepair_ai_search').val(),
      limit: $('#rankrepair_ai_limit').val(),
      offset: $('#rankrepair_ai_offset').val(),
      scan_depth: 3000,
      min_id: $('#rankrepair_ai_min_id').val(),
      max_id: $('#rankrepair_ai_max_id').val()
    })
      .done(r => {
        render(r.data.items);
        const parts = [`Showing ${r.data.items.length} posts`, `inspected ${r.data.inspected || 0}`, `query total ${r.data.found_posts || 0}`];
        if(r.data.issue_filter === 'issues' && r.data.items.length === 0){
          parts.push('Try “All posts” if you want to review every item manually.');
        }
        status(parts.join(' · '));
      })
      .fail(xhr => status(errorMsg(xhr, 'Scan failed.')));
  });

  $('#rankrepair_ai_select_all').on('click', () => { $('.rankrepair-ai-check').prop('checked', true); updateSelectionCount(); });
  $('#rankrepair_ai_clear_selection').on('click', () => { $('.rankrepair-ai-check').prop('checked', false); updateSelectionCount(); });
  $(document).on('change', '.rankrepair-ai-check', updateSelectionCount);

  $('#rankrepair_ai_preview_rule').on('click', function(){
    const selected = ids();
    if(!selected.length) return status('Select posts first.');
    status('Generating bulk preview...');
    ajax('rankrepair_ai_bulk_preview', {post_ids:selected, field:$('#rankrepair_ai_field').val(), rule_action:$('#rankrepair_ai_action').val(), params:JSON.stringify(ruleParams())})
      .done(r => {
        previews = r.data.items || [];
        previews.forEach(p => $(`tr[data-id="${p.post_id}"] .rankrepair-ai-preview`).html(`<div class="rankrepair-ai-before"><b>Before:</b> ${esc(p.before)}</div><div class="rankrepair-ai-after"><b>After:</b> ${esc(p.after)}</div>`));
        status(`Previewed ${previews.length} changes.`);
      }).fail(xhr=>status(errorMsg(xhr, 'Preview failed.')));
  });

  $('#rankrepair_ai_apply_rule').on('click', function(){
    if(!previews.length) return status('Preview a rule first.');
    if(!confirm('Apply previewed changes? A backup will be stored per post.')) return;
    status('Applying changes...');
    ajax('rankrepair_ai_bulk_apply', {items:JSON.stringify(previews)})
      .done(r => { status(r.data.message); $('#rankrepair_ai_scan').trigger('click'); })
      .fail(xhr=>status(errorMsg(xhr, 'Apply failed.')));
  });

  $('#rankrepair_ai_ai_selected').on('click', async function(){
    const selected = ids();
    if(!selected.length) return status('Select posts first.');
    const batchSize = Math.max(1, Math.min(10, parseInt($('#rankrepair_ai_ai_batch_size').val() || '3', 10)));
    const provider = $('#rankrepair_ai_ai_provider').val();
    const model = provider === 'anthropic' ? $('#rankrepair_ai_anthropic_model').val() : (provider === 'gemini' ? $('#rankrepair_ai_gemini_model').val() : $('#rankrepair_ai_openai_model').val());
    const batches = chunk(selected, batchSize);
    aiItems = [];
    $(this).prop('disabled', true);
    try {
      for(let i=0; i<batches.length; i++){
        const delay = aiThrottleDelay(provider, model, batchSize);
        status(`Generating AI suggestions: batch ${i+1} of ${batches.length}. Provider-aware pause ${delay}ms after this batch if needed.`);
        try {
          const r = await ajax('rankrepair_ai_ai_generate', {post_ids:batches[i]});
          const items = (r.data && r.data.items) ? r.data.items : [];
          aiItems = aiItems.concat(items);
          items.forEach(item => {
            if(item.error) $(`tr[data-id="${item.post_id}"] .rankrepair-ai-preview`).html(`<div class="rankrepair-ai-error">${esc(item.error)}</div>`);
            else $(`tr[data-id="${item.post_id}"] .rankrepair-ai-preview`).html(`<div class="rankrepair-ai-ai-suggestion"><div><b>AI Title:</b> ${esc(item.after.seo_title)}</div><div><b>AI Description:</b> ${esc(item.after.meta_description)}</div><div><b>AI Keyword:</b> ${esc(item.after.focus_keyword)}</div><small>${esc(item.reason_for_change)} · ${esc(item.provider || '')} ${esc(item.model || '')}</small><br><br><button class="button button-primary rankrepair-ai-apply-ai-one" data-id="${item.post_id}">Apply this AI suggestion</button></div>`);
          });
        } catch(xhr) {
          status(errorMsg(xhr, `AI generation failed on batch ${i+1}.`));
          break;
        }
        if(i < batches.length - 1) await sleep(aiThrottleDelay(provider, model, batchSize));
      }
      status(`Generated ${aiItems.filter(i=>!i.error).length} AI suggestions. ${aiItems.filter(i=>i.error).length} failed.`);
    } finally {
      $('#rankrepair_ai_ai_selected').prop('disabled', false);
    }
  });



  $('#rankrepair_ai_apply_all_ai').on('click', function(){
    const valid = (aiItems || []).filter(i => !i.error && i.after);
    if(!valid.length) return status('Generate AI suggestions first.');
    if(!confirm(`Apply ${valid.length} AI suggestions? A backup will be stored per post.`)) return;
    status('Applying all AI suggestions...');
    ajax('rankrepair_ai_ai_apply', {items:JSON.stringify(valid)})
      .done(r => { status(r.data.message); aiItems = []; $('#rankrepair_ai_scan').trigger('click'); })
      .fail(xhr => status(errorMsg(xhr, 'AI apply failed.')));
  });

  $(document).on('click', '.rankrepair-ai-apply-ai-one', function(){
    const id = String($(this).data('id'));
    const item = aiItems.find(i => String(i.post_id) === id);
    if(!item) return;
    ajax('rankrepair_ai_ai_apply', {items:JSON.stringify([item])})
      .done(r => { status(r.data.message); $('#rankrepair_ai_scan').trigger('click'); })
      .fail(xhr=>status(errorMsg(xhr, 'AI apply failed.')));
  });

  $(document).on('click', '.rankrepair-ai-rollback', function(){
    if(!confirm('Rollback this post to its latest stored backup?')) return;
    ajax('rankrepair_ai_rollback', {post_id:$(this).data('id')})
      .done(r => { status(r.data.message); $('#rankrepair_ai_scan').trigger('click'); })
      .fail(xhr=>status(errorMsg(xhr, 'Rollback failed.')));
  });

  function updateSeoAfterQuickImprove(postId, data){
    const values = data.values || (data.item && data.item.after) || {};
    const audit = data.audit || {};
    const issues = audit.issues || [];
    const count = issues.length;

    const $scanRow = $(`tr[data-id="${postId}"]`);
    if($scanRow.length){
      const issueHtml = count ? issues.map(i => `<span class="rankrepair-ai-badge">${esc(i)}</span>`).join(' ') : '<span class="rankrepair-ai-ok">Good after AI repair</span>';
      $scanRow.find('.rankrepair-ai-issue-cell').html(issueHtml);
      $scanRow.find('.rankrepair-ai-current-cell').html(`<details open><summary>Current SEO fields</summary><span class="rankrepair-ai-current-field"><b>Title:</b> ${esc(values.seo_title || '') || '<em>Empty</em>'}</span><span class="rankrepair-ai-current-field"><b>Description:</b> ${esc(values.meta_description || '') || '<em>Empty</em>'}</span><span class="rankrepair-ai-current-field"><b>Keyword:</b> ${esc(values.focus_keyword || '') || '<em>Empty</em>'}</span></details>`);
      $scanRow.find('.rankrepair-ai-preview').html(`<div class="rankrepair-ai-ai-suggestion is-applied"><strong>Updated:</strong> SEO metadata was applied successfully.</div>`);
    }

    $(`.rankrepair-ai-listing-cell .rankrepair-ai-quick-ai-inline[data-post-id="${postId}"]`).each(function(){
      const $cell = $(this).closest('.rankrepair-ai-listing-cell');
      const label = count ? `${count} issue${count === 1 ? '' : 's'}` : 'Good';
      const cls = count ? 'is-bad' : 'is-good';
      const title = count ? issues.slice(0,4).join(', ') : 'SEO metadata improved';
      $cell.find('.rankrepair-ai-listing-status').attr('title', title).removeClass('is-bad is-good').addClass(cls).text(label);
    });

    $(`.rankrepair-ai-dashboard-fix[data-post-id="${postId}"]`).each(function(){
      const $row = $(this).closest('.rankrepair-ai-mini-row');
      $row.addClass('is-fixed');
      $(this).removeClass('button-primary').prop('disabled', true).text('Improved');
      if(!count){ $row.find('span').text('SEO metadata updated successfully'); }
    });
  }

  $(document).on('click', '.rankrepair-ai-quick-ai, .rankrepair-ai-quick-ai-inline', function(e){
    e.preventDefault();
    const postId = $(this).data('post-id');
    if(!postId) return;
    const $btn = $(this);
    const oldText = $btn.text();
    if(!confirm('Generate and apply AI SEO metadata to this post now? A backup will be stored.')) return;
    $btn.text('Improving...').addClass('disabled').attr('aria-disabled', 'true');
    ajax('rankrepair_ai_quick_ai_improve', {post_id: postId, quick_nonce: RANKREPAIR_AI.quickNonce})
      .done(r => {
        updateSeoAfterQuickImprove(postId, r.data || {});
        if($('#rankrepair_ai_status').length) status(r.data.message || 'SEO improved.');
        else if(!$btn.hasClass('rankrepair-ai-dashboard-fix') && !$btn.hasClass('rankrepair-ai-quick-ai-inline')) alert(r.data.message || 'SEO improved.');
      })
      .fail(xhr => alert(errorMsg(xhr, 'Could not improve SEO.')))
      .always(() => $btn.text(oldText).removeClass('disabled').removeAttr('aria-disabled'));
  });


  let contentBlueprint = null;
  let imageSeoItem = null;
  function prettyJson(obj){ return '<pre class="rankrepair-ai-json-preview">' + esc(JSON.stringify(obj, null, 2)) + '</pre>'; }
  function renderContentBlueprint(item){
    contentBlueprint = item;
    $('#rankrepair_ai_create_draft_post').prop('disabled', false);
    $('#rankrepair_ai_content_output').html(`
      <div class="rankrepair-ai-output-card">
        <h3>${esc(item.post_title)}</h3>
        <p><strong>Slug:</strong> ${esc(item.slug)}</p>
        <p><strong>SEO title:</strong> ${esc(item.seo_title)}</p>
        <p><strong>Meta description:</strong> ${esc(item.meta_description)}</p>
        <p><strong>Focus keyword:</strong> ${esc(item.focus_keyword)}</p>
        <p><strong>Featured image alt:</strong> ${esc(item.featured_image_alt)}</p>
        <details><summary>Preview content HTML</summary><div class="rankrepair-ai-content-preview">${item.content_html || ''}</div></details>
        <details><summary>Full SEO package JSON</summary>${prettyJson(item)}</details>
      </div>`);
  }

  $('#rankrepair_ai_generate_content_blueprint').on('click', function(){
    const topic = $('#rankrepair_ai_content_prompt').val();
    if(!topic) return $('#rankrepair_ai_content_output').html('<div class="rankrepair-ai-error">Add a prompt first.</div>');
    const $btn = $(this).prop('disabled', true).text('Generating...');
    $('#rankrepair_ai_content_output').html('Generating a WordPress-ready content package...');
    ajax('rankrepair_ai_content_blueprint', {
      topic,
      post_type: $('#rankrepair_ai_content_post_type').val(),
      audience: $('#rankrepair_ai_content_audience').val(),
      tone: $('#rankrepair_ai_content_tone').val(),
      language: $('#rankrepair_ai_language').val()
    }).done(r => renderContentBlueprint(r.data.item))
      .fail(xhr => $('#rankrepair_ai_content_output').html(`<div class="rankrepair-ai-error">${esc(errorMsg(xhr, 'Could not generate content.'))}</div>`))
      .always(() => $btn.prop('disabled', false).text('Generate content package'));
  });

  $('#rankrepair_ai_create_draft_post').on('click', function(){
    if(!contentBlueprint) return;
    const $btn = $(this).prop('disabled', true).text('Creating draft...');
    ajax('rankrepair_ai_create_draft_post', {item: JSON.stringify(contentBlueprint), post_type: $('#rankrepair_ai_content_post_type').val(), post_status: $('#rankrepair_ai_content_status').val()})
      .done(r => $('#rankrepair_ai_content_output').prepend(`<div class="notice notice-success inline"><p>${esc(r.data.message)} <a href="${esc(r.data.edit_link)}" target="_blank">Edit draft #${esc(r.data.post_id)}</a></p></div>`))
      .fail(xhr => $('#rankrepair_ai_content_output').prepend(`<div class="rankrepair-ai-error">${esc(errorMsg(xhr, 'Could not create draft.'))}</div>`))
      .always(() => $btn.prop('disabled', false).text('Create WordPress draft'));
  });

  $('#rankrepair_ai_generate_internal_links').on('click', function(){
    const postId = $('#rankrepair_ai_links_post_id').val();
    if(!postId) return $('#rankrepair_ai_links_output').html('<div class="rankrepair-ai-error">Add a post ID first.</div>');
    const $btn = $(this).prop('disabled', true).text('Finding links...');
    $('#rankrepair_ai_links_output').html('Looking for relevant internal links...');
    ajax('rankrepair_ai_internal_links', {post_id: postId, limit: $('#rankrepair_ai_links_limit').val()})
      .done(r => {
        const links = (r.data.item && r.data.item.links) ? r.data.item.links : [];
        if(!links.length) return $('#rankrepair_ai_links_output').html('No strong internal links were suggested.');
        $('#rankrepair_ai_links_output').html('<ol class="rankrepair-ai-link-suggestions">' + links.map(l => `<li><strong>#${esc(l.post_id)}</strong> — ${esc(l.anchor_text)}<br><small>${esc(l.reason)}</small></li>`).join('') + '</ol>');
      })
      .fail(xhr => $('#rankrepair_ai_links_output').html(`<div class="rankrepair-ai-error">${esc(errorMsg(xhr, 'Could not generate internal links.'))}</div>`))
      .always(() => $btn.prop('disabled', false).text('Suggest internal links'));
  });

  $('#rankrepair_ai_generate_image_seo').on('click', function(){
    const attachmentId = $('#rankrepair_ai_image_attachment_id').val();
    if(!attachmentId) return $('#rankrepair_ai_image_output').html('<div class="rankrepair-ai-error">Add an attachment ID first.</div>');
    const $btn = $(this).prop('disabled', true).text('Generating...');
    $('#rankrepair_ai_image_output').html('Generating image SEO metadata...');
    ajax('rankrepair_ai_image_seo', {attachment_id: attachmentId, context: $('#rankrepair_ai_image_context').val()})
      .done(r => {
        const item = r.data.item || {};
        imageSeoItem = item;
        $('#rankrepair_ai_image_output').html(`<div class="rankrepair-ai-output-card"><p><strong>Alt text:</strong> ${esc(item.alt_text)}</p><p><strong>Title:</strong> ${esc(item.title)}</p><p><strong>Caption:</strong> ${esc(item.caption)}</p><p><strong>Description:</strong> ${esc(item.description)}</p><p><strong>Recommended filename:</strong> ${esc(item.recommended_filename)}</p><button type="button" class="button button-primary" id="rankrepair_ai_apply_image_seo">Apply to media item</button></div>`);
      })
      .fail(xhr => $('#rankrepair_ai_image_output').html(`<div class="rankrepair-ai-error">${esc(errorMsg(xhr, 'Could not generate image SEO.'))}</div>`))
      .always(() => $btn.prop('disabled', false).text('Generate image SEO'));
  });


  $(document).on('click', '#rankrepair_ai_apply_image_seo', function(){
    const attachmentId = $('#rankrepair_ai_image_attachment_id').val();
    if(!attachmentId || !imageSeoItem) return;
    if(!confirm('Apply this metadata to the image attachment?')) return;
    const $btn = $(this).prop('disabled', true).text('Applying...');
    ajax('rankrepair_ai_apply_image_seo', {attachment_id: attachmentId, item: JSON.stringify(imageSeoItem)})
      .done(r => $('#rankrepair_ai_image_output').prepend(`<div class="notice notice-success inline"><p>${esc(r.data.message)}</p></div>`))
      .fail(xhr => $('#rankrepair_ai_image_output').prepend(`<div class="rankrepair-ai-error">${esc(errorMsg(xhr, 'Could not apply image SEO.'))}</div>`))
      .always(() => $btn.prop('disabled', false).text('Apply to media item'));
  });


  $(document).on('click', '#rankrepair_ai_bulk_image_seo', function(){
    const $btn = $(this).prop('disabled', true).text('Improving images...');
    $('#rankrepair_ai_bulk_image_output').html('Scanning media library and generating AI metadata in a safe batch...');
    ajax('rankrepair_ai_bulk_image_seo', {
      limit: $('#rankrepair_ai_media_bulk_limit').val(),
      missing_alt_only: $('#rankrepair_ai_media_bulk_missing_alt').val()
    }).done(r => {
      const items = (r.data && r.data.items) ? r.data.items : [];
      if(!items.length) return $('#rankrepair_ai_bulk_image_output').html('No media items needed metadata updates in this batch.');
      const html = items.map(item => {
        if(item.error) return `<div class="rankrepair-ai-output-card"><strong>#${esc(item.attachment_id)}</strong><div class="rankrepair-ai-error">${esc(item.error)}</div></div>`;
        const a = item.after || {};
        return `<div class="rankrepair-ai-output-card rankrepair-ai-media-suggestion" data-attachment-id="${esc(item.attachment_id)}" data-item="${esc(JSON.stringify(a))}">
          <h3>#${esc(item.attachment_id)} — ${esc(item.filename || '')}</h3>
          <p><strong>Alt:</strong> ${esc(a.alt_text || '')}</p>
          <p><strong>Title:</strong> ${esc(a.title || '')}</p>
          <p><strong>Caption:</strong> ${esc(a.caption || '')}</p>
          <p><strong>Description:</strong> ${esc(a.description || '')}</p>
          <button type="button" class="button button-primary rankrepair-ai-apply-bulk-image-one">Apply this image metadata</button>
        </div>`;
      }).join('');
      $('#rankrepair_ai_bulk_image_output').html(`<div class="rankrepair-ai-card-actions"><button type="button" class="button button-primary" id="rankrepair_ai_apply_all_bulk_images">Apply all image suggestions</button></div>${html}`);
    }).fail(xhr => $('#rankrepair_ai_bulk_image_output').html(`<div class="rankrepair-ai-error">${esc(errorMsg(xhr, 'Could not bulk improve images.'))}</div>`))
      .always(() => $btn.prop('disabled', false).text('Bulk improve with AI'));
  });

  $(document).on('click', '.rankrepair-ai-apply-bulk-image-one', function(){
    const $card = $(this).closest('.rankrepair-ai-media-suggestion');
    ajax('rankrepair_ai_apply_image_seo', {attachment_id: $card.data('attachment-id'), item: $card.attr('data-item')})
      .done(r => $card.prepend(`<div class="notice notice-success inline"><p>${esc(r.data.message)}</p></div>`))
      .fail(xhr => $card.prepend(`<div class="rankrepair-ai-error">${esc(errorMsg(xhr, 'Could not apply image SEO.'))}</div>`));
  });

  $(document).on('click', '#rankrepair_ai_apply_all_bulk_images', async function(){
    const cards = $('.rankrepair-ai-media-suggestion').toArray();
    if(!cards.length) return;
    if(!confirm(`Apply ${cards.length} image metadata suggestions?`)) return;
    for(const card of cards){
      const $card = $(card);
      try { await ajax('rankrepair_ai_apply_image_seo', {attachment_id: $card.data('attachment-id'), item: $card.attr('data-item')}); $card.addClass('rankrepair-ai-applied'); }
      catch(e){ $card.prepend(`<div class="rankrepair-ai-error">${esc(errorMsg(e, 'Could not apply this image.'))}</div>`); }
      await sleep(aiThrottleDelay($('#rankrepair_ai_ai_provider').val(), '', 1));
    }
    $('#rankrepair_ai_bulk_image_output').prepend('<div class="notice notice-success inline"><p>Finished applying bulk image metadata.</p></div>');
  });


  let diviPreviews = {};
  const diviStatus = (msg) => $('#rankrepair_ai_divi_status').html(`<p>${esc(msg)}</p>`);

  function renderDiviRows(items){
    const $tb = $('#rankrepair_ai_divi_table tbody').empty();
    if(!items || !items.length){
      $tb.append('<tr><td colspan="6">No recoverable builder/HTML content found in the current page of results. Try Auto detect, increasing the limit, or checking revisions.</td></tr>');
      return;
    }
    items.forEach(item => {
      const sourceLabel = item.source === 'revision' ? `Revision #${esc(item.revision_id)}` : (item.source === 'elementor_data' ? 'Elementor data' : 'Current content');
      const typeLabel = item.source_type ? item.source_type.toUpperCase() : 'AUTO';
      $tb.append(`<tr data-divi-id="${esc(item.id)}">
        <td><code>#${esc(item.id)}</code></td>
        <td><strong>${esc(item.title)}</strong><br><small>${esc(item.post_type)} · ${esc(item.status)} · <a href="${esc(item.edit_link)}" target="_blank">Edit post</a></small></td>
        <td><span class="rankrepair-ai-badge">${sourceLabel}</span><br><small>${typeLabel} · ${esc(item.shortcode_count || 0)} Divi shortcodes · ${esc(item.elementor_widget_count || 0)} Elementor widgets · ${esc(item.html_block_count || 0)} HTML blocks</small></td>
        <td><small>Current words: ${esc(item.current_word_count)}<br>Recoverable words: ${esc(item.recoverable_word_count)}</small></td>
        <td class="rankrepair-ai-divi-preview-cell"><button type="button" class="button rankrepair-ai-divi-preview" data-post-id="${esc(item.id)}">Preview conversion</button></td>
        <td><button type="button" class="button button-primary rankrepair-ai-divi-apply" data-post-id="${esc(item.id)}" disabled>Apply Gutenberg content</button></td>
      </tr>`);
    });
  }

  $('#rankrepair_ai_divi_scan').on('click', function(){
    diviStatus('Scanning posts, revisions, Elementor data, and HTML content...');
    ajax('rankrepair_ai_divi_scan', {
      source_type: $('#rankrepair_ai_divi_source_type').val(),
      post_type: $('#rankrepair_ai_divi_post_type').val(),
      post_status: $('#rankrepair_ai_divi_post_status').val(),
      search: $('#rankrepair_ai_divi_search').val(),
      limit: $('#rankrepair_ai_divi_limit').val(),
      offset: $('#rankrepair_ai_divi_offset').val()
    }).done(r => {
      renderDiviRows(r.data.items || []);
      diviStatus(`Found ${r.data.items.length} recoverable posts in this scan page. Query total: ${r.data.found_posts || 0}.`);
    }).fail(xhr => diviStatus(errorMsg(xhr, 'Content recovery scan failed.')));
  });

  $(document).on('click', '.rankrepair-ai-divi-preview', function(){
    const postId = $(this).data('post-id');
    const $cell = $(`tr[data-divi-id="${postId}"] .rankrepair-ai-divi-preview-cell`);
    $cell.html('Generating Gutenberg preview...');
    ajax('rankrepair_ai_divi_preview', {post_id: postId, source_type: $('#rankrepair_ai_divi_source_type').val()})
      .done(r => {
        const item = r.data.item || {};
        diviPreviews[postId] = item;
        $cell.html(`<div class="rankrepair-ai-output-card rankrepair-ai-divi-preview-box">
          <p><strong>Source:</strong> ${esc(item.source_type || 'content')} · ${esc(item.source)} ${item.revision_id ? '#' + esc(item.revision_id) : ''}</p>
          <p><strong>Recovered:</strong> ${esc(item.word_count)} words · ${esc(item.heading_count)} headings</p>
          <details><summary>Preview recovered content</summary><div class="rankrepair-ai-content-preview">${item.html_preview || ''}</div></details>
        </div>`);
        $(`tr[data-divi-id="${postId}"] .rankrepair-ai-divi-apply`).prop('disabled', false);
      })
      .fail(xhr => $cell.html(`<div class="rankrepair-ai-error">${esc(errorMsg(xhr, 'Could not preview content recovery.'))}</div>`));
  });

  $(document).on('click', '.rankrepair-ai-divi-apply', function(){
    const postId = $(this).data('post-id');
    const item = diviPreviews[postId];
    if(!item || !item.gutenberg_content) return diviStatus('Preview this post before applying recovery.');
    if(!confirm('Replace this post content with recovered Gutenberg blocks? A backup of the current content and Elementor data will be stored.')) return;
    const $btn = $(this).prop('disabled', true).text('Applying...');
    ajax('rankrepair_ai_divi_apply', {post_id: postId, gutenberg_content: item.gutenberg_content})
      .done(r => {
        diviStatus(`${r.data.message} <a href="${esc(r.data.edit_link)}" target="_blank">Edit recovered post #${esc(r.data.post_id)}</a>`);
        $btn.text('Recovered').prop('disabled', true);
      })
      .fail(xhr => {
        diviStatus(errorMsg(xhr, 'Could not apply content recovery.'));
        $btn.prop('disabled', false).text('Apply Gutenberg content');
      });
  });


  let rewriteItem = null;
  let schemaItem = null;

  $('#rankrepair_ai_run_content_audit').on('click', function(){
    const postId = $('#rankrepair_ai_audit_post_id').val();
    if(!postId) return $('#rankrepair_ai_audit_output').html('<div class="rankrepair-ai-error">Add a post ID first.</div>');
    const $btn = $(this).prop('disabled', true).text('Auditing...');
    $('#rankrepair_ai_audit_output').html('Running AI content audit...');
    ajax('rankrepair_ai_content_audit', {post_id: postId})
      .done(r => $('#rankrepair_ai_audit_output').html(`<div class="rankrepair-ai-output-card"><h3>Audit result</h3>${prettyJson(r.data.item)}</div>`))
      .fail(xhr => $('#rankrepair_ai_audit_output').html(`<div class="rankrepair-ai-error">${esc(errorMsg(xhr, 'Audit failed.'))}</div>`))
      .always(() => $btn.prop('disabled', false).text('Run content audit'));
  });

  $('#rankrepair_ai_generate_rewrite').on('click', function(){
    const postId = $('#rankrepair_ai_rewrite_post_id').val();
    if(!postId) return $('#rankrepair_ai_rewrite_output').html('<div class="rankrepair-ai-error">Add a post ID first.</div>');
    rewriteItem = null;
    $('#rankrepair_ai_apply_rewrite').prop('disabled', true);
    const $btn = $(this).prop('disabled', true).text('Generating...');
    $('#rankrepair_ai_rewrite_output').html('Generating rewrite preview...');
    ajax('rankrepair_ai_rewrite_preview', {post_id: postId, mode: $('#rankrepair_ai_rewrite_mode').val()})
      .done(r => {
        rewriteItem = r.data.item;
        $('#rankrepair_ai_apply_rewrite').prop('disabled', false);
        $('#rankrepair_ai_rewrite_output').html(`<div class="rankrepair-ai-output-card"><h3>Rewrite preview</h3><details open><summary>Preview content</summary><div class="rankrepair-ai-content-preview">${rewriteItem.content_html || ''}</div></details><details><summary>Full rewrite package</summary>${prettyJson(rewriteItem)}</details></div>`);
      })
      .fail(xhr => $('#rankrepair_ai_rewrite_output').html(`<div class="rankrepair-ai-error">${esc(errorMsg(xhr, 'Rewrite failed.'))}</div>`))
      .always(() => $btn.prop('disabled', false).text('Generate rewrite preview'));
  });

  $('#rankrepair_ai_apply_rewrite').on('click', function(){
    const postId = $('#rankrepair_ai_rewrite_post_id').val();
    if(!rewriteItem || !postId) return;
    if(!confirm('Apply this rewrite to the post? The previous content will be backed up.')) return;
    const $btn = $(this).prop('disabled', true).text('Applying...');
    ajax('rankrepair_ai_rewrite_apply', {post_id: postId, item: JSON.stringify(rewriteItem)})
      .done(r => $('#rankrepair_ai_rewrite_output').prepend(`<div class="notice notice-success inline"><p>${esc(r.data.message)} <a href="${esc(r.data.edit_link)}" target="_blank">Edit post</a></p></div>`))
      .fail(xhr => $('#rankrepair_ai_rewrite_output').prepend(`<div class="rankrepair-ai-error">${esc(errorMsg(xhr, 'Could not apply rewrite.'))}</div>`))
      .always(() => $btn.prop('disabled', false).text('Apply rewrite'));
  });

  $('#rankrepair_ai_generate_schema').on('click', function(){
    const postId = $('#rankrepair_ai_schema_post_id').val();
    if(!postId) return $('#rankrepair_ai_schema_output').html('<div class="rankrepair-ai-error">Add a post ID first.</div>');
    schemaItem = null;
    $('#rankrepair_ai_apply_schema').prop('disabled', true);
    const $btn = $(this).prop('disabled', true).text('Generating...');
    $('#rankrepair_ai_schema_output').html('Generating schema JSON-LD...');
    ajax('rankrepair_ai_schema_generate', {post_id: postId, schema_type: $('#rankrepair_ai_schema_type').val()})
      .done(r => {
        schemaItem = r.data.item;
        $('#rankrepair_ai_apply_schema').prop('disabled', false);
        $('#rankrepair_ai_schema_output').html(`<div class="rankrepair-ai-output-card"><h3>${esc(schemaItem.schema_type || 'Schema')}</h3>${prettyJson(schemaItem)}</div>`);
      })
      .fail(xhr => $('#rankrepair_ai_schema_output').html(`<div class="rankrepair-ai-error">${esc(errorMsg(xhr, 'Schema generation failed.'))}</div>`))
      .always(() => $btn.prop('disabled', false).text('Generate schema'));
  });

  $('#rankrepair_ai_apply_schema').on('click', function(){
    const postId = $('#rankrepair_ai_schema_post_id').val();
    if(!schemaItem || !postId) return;
    const $btn = $(this).prop('disabled', true).text('Saving...');
    ajax('rankrepair_ai_schema_apply', {post_id: postId, item: JSON.stringify(schemaItem)})
      .done(r => $('#rankrepair_ai_schema_output').prepend(`<div class="notice notice-success inline"><p>${esc(r.data.message)}</p></div>`))
      .fail(xhr => $('#rankrepair_ai_schema_output').prepend(`<div class="rankrepair-ai-error">${esc(errorMsg(xhr, 'Could not save schema.'))}</div>`))
      .always(() => $btn.prop('disabled', false).text('Save schema'));
  });

  $('#rankrepair_ai_generate_content_plan').on('click', function(){
    const topic = $('#rankrepair_ai_plan_topic').val();
    if(!topic) return $('#rankrepair_ai_plan_output').html('<div class="rankrepair-ai-error">Add a topic or business niche first.</div>');
    const $btn = $(this).prop('disabled', true).text('Planning...');
    $('#rankrepair_ai_plan_output').html('Generating content roadmap...');
    ajax('rankrepair_ai_content_plan', {topic, audience: $('#rankrepair_ai_plan_audience').val(), timeframe: $('#rankrepair_ai_plan_timeframe').val()})
      .done(r => $('#rankrepair_ai_plan_output').html(`<div class="rankrepair-ai-output-card"><h3>Content plan</h3>${prettyJson(r.data.item)}</div>`))
      .fail(xhr => $('#rankrepair_ai_plan_output').html(`<div class="rankrepair-ai-error">${esc(errorMsg(xhr, 'Content plan failed.'))}</div>`))
      .always(() => $btn.prop('disabled', false).text('Generate content plan'));
  });

  function editorOutput(postId, html){ $('#rankrepair_ai_editor_output_' + postId).html(html); }
  $(document).on('click', '.rankrepair-ai-editor-improve', function(e){
    e.preventDefault();
    const postId = $(this).data('post-id');
    if(!postId) return;
    const $btn = $(this), oldText = $btn.text();
    if(!confirm('Generate and apply improved SEO metadata for this item? This updates SEO fields only and stores a backup.')) return;
    editorOutput(postId, '<p>Improving SEO metadata...</p>');
    $btn.prop('disabled', true).text('Improving...');
    ajax('rankrepair_ai_quick_ai_improve', {post_id: postId, quick_nonce: RANKREPAIR_AI.quickNonce})
      .done(r => { updateSeoAfterQuickImprove(postId, r.data || {}); editorOutput(postId, `<div class="notice notice-success inline"><p>${esc(r.data.message || 'SEO improved. Values updated below.')}</p></div>`); })
      .fail(xhr => editorOutput(postId, `<div class="notice notice-error inline"><p>${esc(errorMsg(xhr, 'Could not improve SEO.'))}</p></div>`))
      .always(() => $btn.prop('disabled', false).text(oldText));
  });
  $(document).on('click', '.rankrepair-ai-editor-audit', function(e){
    e.preventDefault();
    const postId = $(this).data('post-id');
    editorOutput(postId, '<p>Running audit...</p>');
    ajax('rankrepair_ai_editor_audit', {post_id: postId})
      .done(r => editorOutput(postId, `<div class="rankrepair-ai-output-card"><strong>Audit result</strong>${prettyJson(r.data.item)}</div>`))
      .fail(xhr => editorOutput(postId, `<div class="notice notice-error inline"><p>${esc(errorMsg(xhr, 'Audit failed.'))}</p></div>`));
  });
  $(document).on('click', '.rankrepair-ai-editor-schema', function(e){
    e.preventDefault();
    const postId = $(this).data('post-id');
    editorOutput(postId, '<p>Generating schema...</p>');
    ajax('rankrepair_ai_editor_generate_schema', {post_id: postId, schema_type: 'auto'})
      .done(r => editorOutput(postId, `<div class="rankrepair-ai-output-card"><strong>Schema preview</strong>${prettyJson(r.data.item)}<p><em>Use the main RankRepair Schema Builder page to save this schema after review.</em></p></div>`))
      .fail(xhr => editorOutput(postId, `<div class="notice notice-error inline"><p>${esc(errorMsg(xhr, 'Schema failed.'))}</p></div>`));
  });
  $(document).on('click', '.rankrepair-ai-editor-rewrite', function(e){
    e.preventDefault();
    const postId = $(this).data('post-id');
    editorOutput(postId, '<p>Generating rewrite preview...</p>');
    ajax('rankrepair_ai_editor_rewrite_preview', {post_id: postId, mode: 'seo_readability'})
      .done(r => editorOutput(postId, `<div class="rankrepair-ai-output-card"><strong>Rewrite preview</strong><details open><summary>Content</summary><div class="rankrepair-ai-content-preview">${(r.data.item && r.data.item.content_html) || ''}</div></details><p><em>For safety, apply rewrites from the main RankRepair Rewrite module.</em></p></div>`))
      .fail(xhr => editorOutput(postId, `<div class="notice notice-error inline"><p>${esc(errorMsg(xhr, 'Rewrite preview failed.'))}</p></div>`));
  });
  $(document).on('click', '.rankrepair-ai-editor-recovery', function(e){
    e.preventDefault();
    const postId = $(this).data('post-id');
    editorOutput(postId, '<p>Checking builder/HTML recovery...</p>');
    ajax('rankrepair_ai_editor_recovery_preview', {post_id: postId, source_type: 'auto'})
      .done(r => editorOutput(postId, `<div class="rankrepair-ai-output-card"><strong>Recovery preview</strong><p>${esc((r.data.item && r.data.item.word_count) || 0)} words recovered · ${esc((r.data.item && r.data.item.heading_count) || 0)} headings</p><details><summary>Preview</summary><div class="rankrepair-ai-content-preview">${(r.data.item && r.data.item.html_preview) || ''}</div></details><p><em>Apply recovery from the main Content Recovery module after reviewing backups.</em></p></div>`))
      .fail(xhr => editorOutput(postId, `<div class="notice notice-error inline"><p>${esc(errorMsg(xhr, 'Recovery preview failed.'))}</p></div>`));
  });


  function renderImageRecovery(items){
    const $tb = $('#rankrepair_ai_image_recovery_table tbody').empty();
    if(!items || !items.length){ $tb.append('<tr><td colspan="5">No image issues found in this batch.</td></tr>'); return; }
    items.forEach(item => {
      const issues = (item.issues || []).map(i => `<span class="rankrepair-ai-badge">${esc(i.label || i.type)}</span>`).join(' ');
      const firstIssue = esc(JSON.stringify((item.issues || [])[0] || {}));
      $tb.append(`<tr data-post-id="${item.post_id}">
        <td><code>#${item.post_id}</code></td>
        <td><strong>${esc(item.post_title)}</strong><br><small>${esc(item.post_type)} · <a href="${esc(item.edit_link)}" target="_blank">Edit</a></small></td>
        <td>${issues}</td>
        <td class="rankrepair-ai-imgrec-suggestion">No suggestion yet.</td>
        <td class="rankrepair-ai-row-actions">
          <button type="button" class="button rankrepair-ai-imgrec-suggest" data-post-id="${item.post_id}" data-issue='${firstIssue}'>AI image guidance</button>
          <div class="rankrepair-ai-imgrec-apply">
            <input type="number" class="rankrepair-ai-imgrec-attachment" placeholder="Attachment ID" min="1">
            <input type="url" class="rankrepair-ai-imgrec-url" placeholder="or legal image URL">
            <input type="text" class="rankrepair-ai-imgrec-alt" placeholder="Alt text">
            <button type="button" class="button button-primary rankrepair-ai-imgrec-apply-btn" data-post-id="${item.post_id}">Set featured image</button>
          </div>
        </td>
      </tr>`);
    });
  }

  $('#rankrepair_ai_image_recovery_scan').on('click', function(){
    const $btn = $(this).prop('disabled', true).text('Scanning...');
    $('#rankrepair_ai_image_recovery_status').text('Scanning image issues...');
    ajax('rankrepair_ai_image_recovery_scan', {
      post_type: $('#rankrepair_ai_imgrec_post_type').val(),
      post_status: $('#rankrepair_ai_imgrec_post_status').val(),
      mode: $('#rankrepair_ai_imgrec_mode').val(),
      limit: $('#rankrepair_ai_imgrec_limit').val(),
      offset: $('#rankrepair_ai_imgrec_offset').val()
    }).done(r => {
      renderImageRecovery(r.data.items || []);
      $('#rankrepair_ai_image_recovery_status').text(`Found ${(r.data.items || []).length} posts with image issues · inspected ${r.data.inspected || 0} · query total ${r.data.found_posts || 0}.`);
    }).fail(xhr => $('#rankrepair_ai_image_recovery_status').html(`<span class="rankrepair-ai-error">${esc(errorMsg(xhr, 'Image recovery scan failed.'))}</span>`))
      .always(() => $btn.prop('disabled', false).text('Scan images'));
  });

  $(document).on('click', '.rankrepair-ai-imgrec-suggest', function(){
    const postId = $(this).data('post-id');
    const issue = $(this).attr('data-issue') || '{}';
    const $row = $(`tr[data-post-id="${postId}"]`);
    const $btn = $(this).prop('disabled', true).text('Thinking...');
    $row.find('.rankrepair-ai-imgrec-suggestion').html('Generating AI image guidance...');
    ajax('rankrepair_ai_image_recovery_suggest', {post_id: postId, issue})
      .done(r => {
        const it = r.data.item || {};
        $row.find('.rankrepair-ai-imgrec-suggestion').html(`<div class="rankrepair-ai-output-card"><strong>Search query:</strong> ${esc(it.image_search_query)}<br><strong>Image prompt:</strong> ${esc(it.image_prompt)}<br><strong>Alt:</strong> ${esc(it.alt_text)}<br><strong>Filename:</strong> ${esc(it.recommended_filename)}${it.usage_notes ? '<br><small>'+esc((it.usage_notes || []).join(' · '))+'</small>' : ''}</div>`);
        $row.find('.rankrepair-ai-imgrec-alt').val(it.alt_text || '');
      })
      .fail(xhr => $row.find('.rankrepair-ai-imgrec-suggestion').html(`<div class="rankrepair-ai-error">${esc(errorMsg(xhr, 'Could not generate image guidance.'))}</div>`))
      .always(() => $btn.prop('disabled', false).text('AI image guidance'));
  });

  $(document).on('click', '.rankrepair-ai-imgrec-apply-btn', function(){
    const postId = $(this).data('post-id');
    const $row = $(`tr[data-post-id="${postId}"]`);
    if(!confirm('Replace/set the featured image for this post? RankRepair stores a backup of the previous image ID.')) return;
    const $btn = $(this).prop('disabled', true).text('Applying...');
    ajax('rankrepair_ai_image_recovery_apply', {
      post_id: postId,
      target: 'featured_image',
      attachment_id: $row.find('.rankrepair-ai-imgrec-attachment').val(),
      image_url: $row.find('.rankrepair-ai-imgrec-url').val(),
      alt_text: $row.find('.rankrepair-ai-imgrec-alt').val()
    }).done(r => {
      $row.find('.rankrepair-ai-imgrec-suggestion').prepend(`<div class="notice notice-success inline"><p>${esc(r.data.message || 'Image updated.')}</p></div>`);
    }).fail(xhr => $row.find('.rankrepair-ai-imgrec-suggestion').prepend(`<div class="rankrepair-ai-error">${esc(errorMsg(xhr, 'Could not apply image replacement.'))}</div>`))
      .always(() => $btn.prop('disabled', false).text('Set featured image'));
  });

  $(document).on('click', '.rankrepair-ai-editor-image-audit', function(e){
    e.preventDefault();
    const postId = $(this).data('post-id');
    editorOutput(postId, '<p>Auditing image issues...</p>');
    ajax('rankrepair_ai_image_recovery_scan', {post_type: 'any', post_status: 'any', limit: 1, offset: 0, mode: 'all'})
      .done(() => {
        ajax('rankrepair_ai_image_recovery_suggest', {post_id: postId, issue: '{}'})
          .done(r => editorOutput(postId, `<div class="rankrepair-ai-output-card"><strong>Image guidance</strong>${prettyJson(r.data.item)}<p><em>Use the main Image Recovery module to apply replacements safely.</em></p></div>`))
          .fail(xhr => editorOutput(postId, `<div class="notice notice-error inline"><p>${esc(errorMsg(xhr, 'Image guidance failed.'))}</p></div>`));
      })
      .fail(xhr => editorOutput(postId, `<div class="notice notice-error inline"><p>${esc(errorMsg(xhr, 'Image audit failed.'))}</p></div>`));
  });


})(jQuery);
