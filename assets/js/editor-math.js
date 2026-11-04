/**
 * Gutenberg 数学公式自动转换
 *
 * 规则：
 *  - 独占一段的 $$...$$（内容非空） -> core/math 区块
 *  - 段落中的 $...$（内容非空）     -> 行内 core/math 格式
 *  - 段落中的 $$...$$               -> 不处理，保持普通文本
 *  - $$$$ 等空内容                   -> 不闭合、不转换
 */
(function (wp) {
  'use strict';
  if (!wp || !wp.data || !wp.blocks || !wp.domReady) {
    return;
  }

  var select = wp.data.select;
  var dispatch = wp.data.dispatch;
  var createBlock = wp.blocks.createBlock;
  var latexToMathMLPromise = null;
  var scheduled = false;
  var converting = false;

  function getLatexToMathML() {
    if (!latexToMathMLPromise) {
      latexToMathMLPromise = import('@wordpress/latex-to-mathml').then(function (mod) {
        return mod.default || mod;
      });
    }
    return latexToMathMLPromise;
  }

  function escapeAttr(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function htmlToText(html) {
    var div = document.createElement('div');
    div.innerHTML = String(html || '').replace(/<br\s*\/?>/gi, '\n');
    return div.textContent || '';
  }

  function hasUnsupportedTags(html, allowed) {
    var re = /<([a-zA-Z][a-zA-Z0-9:-]*)\b[^>]*>/g;
    var m;
    var allow = allowed || [];
    while ((m = re.exec(html)) !== null) {
      var tag = m[1].toLowerCase();
      if (allow.indexOf(tag) === -1) {
        return true;
      }
    }
    return false;
  }

  /** 只转换单个 $...$，跳过 $$...$$、\$ 与代码块内容 */
  function convertInlineHtml(html, toMathML) {
    if (html.indexOf('$') === -1) {
      return html;
    }
    // 包含链接、加粗等富文本标签时暂不处理，避免破坏 HTML 结构
    if (hasUnsupportedTags(html, ['br'])) {
      return html;
    }

    var out = '';
    var i = 0;
    while (i < html.length) {
      var ch = html.charAt(i);

      if (ch === '\\' && html.charAt(i + 1) === '$') {
        out += '\\$';
        i += 2;
        continue;
      }

      if (ch !== '$') {
        out += ch;
        i++;
        continue;
      }

      // $$...$$ 原样保留，不做行内数学
      if (html.charAt(i + 1) === '$') {
        out += '$$';
        i += 2;
        continue;
      }

      // 找下一个未转义的单个 $
      var j = i + 1;
      var found = -1;
      while (j < html.length) {
        var c = html.charAt(j);
        if (c === '\\') {
          j += 2;
          continue;
        }
        if (c === '$') {
          if (html.charAt(j + 1) === '$') {
            j += 2;
            continue;
          }
          found = j;
          break;
        }
        if (c === '\n') {
          break;
        }
        j++;
      }

      if (found === -1) {
        out += ch;
        i++;
        continue;
      }

      var latex = html.slice(i + 1, found);
      if (latex.trim() === '') {
        out += ch;
        i++;
        continue;
      }

      var mathML = '';
      try {
        mathML = toMathML(latex, { displayMode: false });
      } catch (e) {
        // 解析失败时保持原文，避免输入被吞掉
        out += html.slice(i, found + 1);
        i = found + 1;
        continue;
      }

      out += '<math data-latex="' + escapeAttr(latex) + '">' + mathML + '</math>';
      i = found + 1;
    }

    return out;
  }

  /**
   * 处理用户按 Markdown 习惯输入的：
   *   $$
   *   x \le m
   *   $$
   * 在 Gutenberg 里这会变成三个段落，需要把整段序列合成一个 Math 区块。
   */
  function tryConvertBlockSequence(blocks) {
    for (var i = 0; i < blocks.length; i++) {
      var open = blocks[i];
      if (open.name !== 'core/paragraph' || String(open.attributes.content || '').trim() !== '$$') {
        continue;
      }
      var closeIndex = -1;
      for (var j = i + 1; j < blocks.length; j++) {
        if (blocks[j].name !== 'core/paragraph') {
          break;
        }
        if (String(blocks[j].attributes.content || '').trim() === '$$') {
          closeIndex = j;
          break;
        }
      }
      if (closeIndex === -1) {
        continue;
      }

      var lines = [];
      var ok = true;
      for (var k = i + 1; k < closeIndex; k++) {
        var inner = blocks[k];
        if (inner.name !== 'core/paragraph') {
          ok = false;
          break;
        }
        var text = htmlToText(inner.attributes.content || '').trim();
        if (text !== '') {
          lines.push(text);
        }
      }
      var latex = lines.join('\n').trim();
      if (!ok || latex === '') {
        continue;
      }

      var ids = [];
      for (var n = i; n <= closeIndex; n++) {
        ids.push(blocks[n].clientId);
      }
      dispatch('core/block-editor').replaceBlocks(
        ids,
        createBlock('core/math', { latex: latex })
      );
      return true;
    }
    return false;
  }

  function collectParagraphs(blocks, out) {
    for (var i = 0; i < blocks.length; i++) {
      var b = blocks[i];
      if (b.name === 'core/paragraph') {
        out.push(b);
      }
      if (b.innerBlocks && b.innerBlocks.length) {
        collectParagraphs(b.innerBlocks, out);
      }
    }
    return out;
  }

  function processBlocks() {
    if (converting) {
      return;
    }
    var paragraphs = collectParagraphs(select('core/block-editor').getBlocks(), []);
    if (!paragraphs.length) {
      return;
    }

    converting = true;
    getLatexToMathML().then(function (toMathML) {
      // 重新取一次，避免异步期间数据已变化
      var topBlocks = select('core/block-editor').getBlocks();
      if (tryConvertBlockSequence(topBlocks)) {
        return;
      }
      var blocks = collectParagraphs(topBlocks, []);
      blocks.forEach(function (block) {
        var content = String(block.attributes.content || '');
        if (content.indexOf('$') === -1) {
          return;
        }

        var trimmed = content.trim();
        var blockMatch = trimmed.match(/^\$\$([\s\S]*?)\$\$$/);
        if (blockMatch && blockMatch[1].trim() !== '') {
          // 独占一段：转块级公式
          if (hasUnsupportedTags(content, ['br'])) {
            return;
          }
          var latex = htmlToText(blockMatch[1]).trim();
          if (latex === '') {
            return;
          }
          dispatch('core/block-editor').replaceBlock(
            block.clientId,
            createBlock('core/math', { latex: latex })
          );
          return;
        }

        // 段落中的单美元行内公式
        var next = convertInlineHtml(content, toMathML);
        if (next !== content) {
          dispatch('core/block-editor').updateBlockAttributes(block.clientId, { content: next });
        }
      });
    }).catch(function (err) {
      if (window.console && console.warn) {
        console.warn('[editor-math] 初始化失败', err);
      }
    }).then(function () {
      converting = false;
    });
  }

  function schedule() {
    if (scheduled) {
      return;
    }
    scheduled = true;
    window.setTimeout(function () {
      scheduled = false;
      processBlocks();
    }, 300);
  }

  wp.domReady(function () {
    // 编辑器初始化后跑一次，处理粘贴/已有内容
    window.setTimeout(processBlocks, 1200);
    wp.data.subscribe(schedule);
  });
})(window.wp);
