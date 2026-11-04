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

  /**
   * 把一段纯文本里的 $...$ 转成 <math> 节点。
   * 只处理文本节点，保留 <strong>/<a>/<em> 等已有格式。
   */
  function appendInlineMath(text, toMathML, frag) {
    if (text.indexOf('$') === -1) {
      frag.appendChild(document.createTextNode(text));
      return;
    }

    var out = '';
    var i = 0;
    var changed = false;

    function flush() {
      if (out !== '') {
        frag.appendChild(document.createTextNode(out));
        out = '';
      }
    }

    while (i < text.length) {
      var ch = text.charAt(i);

      if (ch === '\\' && text.charAt(i + 1) === '$') {
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
      if (text.charAt(i + 1) === '$') {
        out += '$$';
        i += 2;
        continue;
      }

      var j = i + 1;
      var found = -1;
      while (j < text.length) {
        var c = text.charAt(j);
        if (c === '\\') {
          j += 2;
          continue;
        }
        if (c === '$') {
          if (text.charAt(j + 1) === '$') {
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

      var latex = text.slice(i + 1, found);
      if (latex.trim() === '') {
        out += ch;
        i++;
        continue;
      }

      var mathML = '';
      try {
        mathML = toMathML(latex, { displayMode: false });
      } catch (e) {
        out += text.slice(i, found + 1);
        i = found + 1;
        continue;
      }

      flush();
      var math = document.createElement('math');
      math.setAttribute('data-latex', latex);
      math.innerHTML = mathML;
      frag.appendChild(math);
      changed = true;
      i = found + 1;
    }

    flush();
    return changed;
  }

  /** 转换段落 HTML：只用 DOM 处理文本节点，避免破坏加粗、链接等标签 */
  function convertInlineHtml(html, toMathML) {
    if (html.indexOf('$') === -1) {
      return html;
    }

    var root = document.createElement('div');
    root.innerHTML = html;

    var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
    var nodes = [];
    while (walker.nextNode()) {
      nodes.push(walker.currentNode);
    }

    var changed = false;
    nodes.forEach(function (node) {
      var parent = node.parentElement;
      if (parent && parent.closest('code,pre,script,style,math')) {
        return;
      }
      var text = node.nodeValue || '';
      if (text.indexOf('$') === -1) {
        return;
      }
      var frag = document.createDocumentFragment();
      if (appendInlineMath(text, toMathML, frag)) {
        node.parentNode.replaceChild(frag, node);
        changed = true;
      }
    });

    return changed ? root.innerHTML : html;
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

  function getEditorDocument() {
    var iframe = document.querySelector('iframe[name="editor-canvas"]');
    return iframe && iframe.contentDocument ? iframe.contentDocument : document;
  }

  function getEditorWindow() {
    var iframe = document.querySelector('iframe[name="editor-canvas"]');
    return iframe && iframe.contentWindow ? iframe.contentWindow : window;
  }

  /** 记录当前光标位置，用于转换后恢复，避免光标跳到行首 */
  function captureCaret(clientId) {
    var doc = getEditorDocument();
    var win = getEditorWindow();
    var sel = win.getSelection ? win.getSelection() : null;
    if (!sel || !sel.rangeCount || !sel.anchorNode) {
      return null;
    }
    var node = sel.anchorNode;
    if (!doc.contains(node)) {
      return null;
    }
    var el = node.nodeType === 3 ? node.parentElement : node;
    var editable = el && el.closest ? el.closest('[contenteditable="true"]') : null;
    if (!editable) {
      return null;
    }
    var blockEl = editable.closest('[data-block]');
    if (!blockEl || blockEl.getAttribute('data-block') !== clientId) {
      return null;
    }
    var range = doc.createRange();
    range.selectNodeContents(editable);
    range.setEnd(sel.anchorNode, sel.anchorOffset);
    var offset = range.toString().length;
    var total = (editable.textContent || '').length;
    return { doc: doc, win: win, editable: editable, atEnd: offset >= total - 1 };
  }

  function restoreCaretAfterMath(clientId, snapshot) {
    if (!snapshot) {
      return;
    }
    var doc = snapshot.doc;
    var blockEl = doc.querySelector('[data-block="' + (window.CSS && CSS.escape ? CSS.escape(clientId) : clientId) + '"]');
    if (!blockEl) {
      return;
    }
    var maths = blockEl.querySelectorAll('math[data-latex]');
    if (!maths.length) {
      return;
    }
    var target = maths[maths.length - 1];
    var editable = target.closest('[contenteditable="true"]');
    if (!editable) {
      return;
    }

    // Gutenberg 会把行内 math 包在 span 里，并在后面放一个零宽字符。
    // 光标要落在 span 外面、零宽字符后面，否则会像“卡在数学块里”。
    var anchor = target.parentElement;
    if (!anchor || anchor === editable || anchor.closest('[contenteditable="true"]') !== editable) {
      anchor = target;
    }

    var range = doc.createRange();
    range.setStartAfter(anchor);
    range.collapse(true);

    var after = anchor.nextSibling;
    if (after && after.nodeType === 3) {
      var value = after.nodeValue || '';
      if (value.charAt(0) === '\uFEFF') {
        range.setStart(after, 1);
        range.collapse(true);
      }
    }
    var sel = snapshot.win.getSelection ? snapshot.win.getSelection() : null;
    if (!sel) {
      return;
    }
    sel.removeAllRanges();
    sel.addRange(range);
    editable.focus();
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
        var caret = captureCaret(block.clientId);
        var next = convertInlineHtml(content, toMathML);
        if (next !== content) {
          dispatch('core/block-editor').updateBlockAttributes(block.clientId, { content: next });
          if (caret && caret.atEnd) {
            window.setTimeout(function () {
              restoreCaretAfterMath(block.clientId, caret);
            }, 30);
          }
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
