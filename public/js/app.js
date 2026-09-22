// =====================================================================
// Gestão de Compras e Estoque — comportamentos compartilhados (Fase 5)
// =====================================================================

document.addEventListener('DOMContentLoaded', function () {

  // ---------- Sidebar recolhível ----------
  // Só clique abre e só clique fecha: o botão da topbar abre, o "X" do menu
  // fecha. Nada de abrir por proximidade do cursor — isso disparava sozinho
  // quando o mouse passava rente à borda esquerda.
  //
  // Em tela grande o menu EMPURRA o conteúdo (ver .gc-sidebar no CSS) e fica
  // aberto até mandarem fechar, inclusive entre uma página e outra: sem
  // lembrar o estado, ele fecharia a cada clique no próprio menu, que é
  // justamente quando o usuário quer que ele continue lá.
  //
  // Em tela pequena a regra é outra, porque empurrar não cabe: sobrepõe, não
  // é lembrado entre páginas e fecha ao tocar fora.
  const toggleBtn = document.getElementById('gc-sidebar-toggle');
  const fecharBtn = document.getElementById('gc-sidebar-fechar');
  const sidebar = document.querySelector('.gc-sidebar');

  if (sidebar) {
    const CHAVE = 'gc.menu.aberto';
    const telaGrande = () => window.matchMedia('(min-width: 992px)').matches;

    const guardar = (aberto) => {
      try {
        if (aberto) {
          localStorage.setItem(CHAVE, '1');
        } else {
          localStorage.removeItem(CHAVE);
        }
      } catch (e) {
        // Navegador com storage bloqueado: o menu funciona, só não é lembrado.
      }
    };

    const definir = (aberto, lembrar = true) => {
      sidebar.classList.toggle('show', aberto);
      if (lembrar && telaGrande()) guardar(aberto);
    };

    // Restaura o estado só na tela grande: no celular, abrir sozinho cobriria
    // a página inteira antes de o usuário pedir.
    try {
      if (telaGrande() && localStorage.getItem(CHAVE) === '1') {
        // Sem transição no carregamento: o menu já nasce aberto em vez de
        // deslizar na cara de quem abriu a página.
        sidebar.style.transition = 'none';
        sidebar.classList.add('show');
        requestAnimationFrame(() => { sidebar.style.transition = ''; });
      }
    } catch (e) { /* storage indisponível */ }

    if (toggleBtn) {
      toggleBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        definir(! sidebar.classList.contains('show'));
      });
    }

    if (fecharBtn) {
      fecharBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        definir(false);
      });
    }

    // Tocar fora fecha APENAS na tela pequena, onde o menu sobrepõe o conteúdo.
    document.addEventListener('click', (e) => {
      if (! telaGrande()
          && sidebar.classList.contains('show')
          && ! sidebar.contains(e.target)
          && ! toggleBtn?.contains(e.target)) {
        definir(false, false);
      }
    });

    // Ao encolher a janela, o menu aberto viraria uma sobreposição que ninguém
    // pediu — fecha, sem esquecer a preferência da tela grande.
    window.matchMedia('(min-width: 992px)').addEventListener('change', (evento) => {
      if (! evento.matches) {
        sidebar.classList.remove('show');
      } else {
        try {
          sidebar.classList.toggle('show', localStorage.getItem(CHAVE) === '1');
        } catch (e) { /* storage indisponível */ }
      }
    });
  }

  // ---------- Botões flutuantes de rolagem do menu ----------
  // Complementam (não substituem) a roda do mouse / touchpad / toque.
  // Aparecem só quando há conteúdo oculto na direção correspondente.
  const navScroll = document.querySelector('.gc-nav-scroll');
  const nav = navScroll ? navScroll.querySelector('.gc-nav') : null;
  const btnUp = navScroll ? navScroll.querySelector('.gc-nav-scroll-up') : null;
  const btnDown = navScroll ? navScroll.querySelector('.gc-nav-scroll-down') : null;

  if (nav && btnUp && btnDown) {
    const TOLERANCIA = 2; // px — evita "quase no fim" por arredondamento

    const definirVisivel = (btn, visivel) => {
      if (btn.hidden === !visivel) return; // sem mudança → não mexe no DOM
      btn.hidden = !visivel;
      btn.setAttribute('aria-hidden', visivel ? 'false' : 'true');
    };

    const atualizarBotoes = () => {
      const temOverflow = nav.scrollHeight - nav.clientHeight > TOLERANCIA;
      const podeSubir = temOverflow && nav.scrollTop > TOLERANCIA;
      const podeDescer = temOverflow
        && nav.scrollTop < nav.scrollHeight - nav.clientHeight - TOLERANCIA;
      definirVisivel(btnUp, podeSubir);
      definirVisivel(btnDown, podeDescer);
    };

    const rolar = (fator) => {
      nav.scrollBy({ top: nav.clientHeight * fator, behavior: 'smooth' });
    };

    btnUp.addEventListener('click', () => rolar(-0.7));
    btnDown.addEventListener('click', () => rolar(0.7));

    nav.addEventListener('scroll', atualizarBotoes, { passive: true });
    window.addEventListener('resize', atualizarBotoes);

    // Abertura/fechamento de submenu (Bootstrap collapse) muda a altura do menu.
    nav.addEventListener('shown.bs.collapse', atualizarBotoes);
    nav.addEventListener('hidden.bs.collapse', atualizarBotoes);
    // Durante a animação do collapse a altura varia continuamente.
    nav.addEventListener('transitionend', atualizarBotoes);

    // Mudanças de conteúdo/altura não cobertas pelos eventos acima.
    if (typeof ResizeObserver !== 'undefined') {
      new ResizeObserver(atualizarBotoes).observe(nav);
    }

    atualizarBotoes(); // estado inicial, sem flicker (botões já começam hidden)
  }

  // ---------- Toasts de flash message (sucesso / erro) ----------
  document.querySelectorAll('.toast').forEach((toastEl) => {
    const toast = new bootstrap.Toast(toastEl, { delay: 5000 });
    toast.show();
  });

  // ---------- Modal de confirmação genérico ----------
  // Qualquer botão/link com data-confirm-delete abre o #gc-confirm-modal,
  // ajusta a mensagem e o action do form antes de exibir.
  const confirmModalEl = document.getElementById('gc-confirm-modal');
  if (confirmModalEl) {
    const confirmModal = new bootstrap.Modal(confirmModalEl);
    const confirmForm = confirmModalEl.querySelector('form');
    const confirmMessage = confirmModalEl.querySelector('[data-confirm-message]');
    const confirmTitle = confirmModalEl.querySelector('[data-confirm-title]');
    const confirmMethodField = confirmModalEl.querySelector('[name="_method"]');

    document.querySelectorAll('[data-confirm-delete]').forEach((trigger) => {
      trigger.addEventListener('click', (e) => {
        e.preventDefault();
        confirmForm.action = trigger.dataset.url || '#';
        confirmMessage.textContent = trigger.dataset.message || 'Tem certeza que deseja confirmar esta ação?';
        confirmTitle.textContent = trigger.dataset.title || 'Confirmar ação';
        if (confirmMethodField) {
          confirmMethodField.value = trigger.dataset.method || 'DELETE';
        }
        confirmModal.show();
      });
    });
  }

  // O estado ativo da sidebar é resolvido no servidor via request()->routeIs()
  // no layout (app.blade.php). Não marcamos active por prefixo de URL aqui —
  // isso ativava indevidamente itens-pai (ex.: /estoque para /estoque/recontagens).

  // ---------- Inicializa tooltips do Bootstrap ----------
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));

  // ---------- Confirmação declarativa (data-gc-confirm) ----------
  // Substitui os antigos onsubmit/onclick="return confirm(...)".
  // Em <form data-gc-confirm> intercepta o submit; em <button data-gc-confirm>
  // (ou link) intercepta o clique. Só prossegue após o usuário confirmar.
  document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-gc-confirm')) {
      return;
    }
    if (form.dataset.gcConfirmed === '1') {
      return; // já confirmado — deixa seguir
    }
    e.preventDefault();
    window.gcConfirm(gcLerOpcoesConfirmacao(form)).then((confirmado) => {
      if (confirmado) {
        form.dataset.gcConfirmed = '1';
        form.submit();
      }
    });
  }, true);

  document.addEventListener('click', function (e) {
    const trigger = e.target.closest('[data-gc-confirm]');
    if (!trigger || trigger.tagName === 'FORM' || trigger.dataset.gcConfirmed === '1') {
      return;
    }
    e.preventDefault();
    window.gcConfirm(gcLerOpcoesConfirmacao(trigger)).then((confirmado) => {
      if (!confirmado) {
        return;
      }
      trigger.dataset.gcConfirmed = '1';
      const form = trigger.form || trigger.closest('form');
      if (form) {
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit(trigger.type === 'submit' ? trigger : undefined);
        } else {
          form.submit();
        }
      } else if (trigger.href) {
        window.location.href = trigger.href;
      } else {
        trigger.click();
      }
    });
  }, true);

  // ---------- Exportação de relatórios (PDF/Excel): loading + cancelamento ----------
  gcInicializarExportacao();
});

// =====================================================================
// Repetidor de itens — usado em compras/create.blade.php
// Disponível globalmente para ser chamado pela view.
// =====================================================================
function gcAdicionarItem(template, container) {
  const idx = container.querySelectorAll('.gc-item-row').length;
  const html = template.innerHTML.replaceAll('__INDEX__', idx);
  const wrapper = document.createElement('div');
  wrapper.innerHTML = html;
  container.appendChild(wrapper.firstElementChild);
}

function gcRemoverItem(btn) {
  const row = btn.closest('.gc-item-row');
  const container = row.parentElement;
  if (container.querySelectorAll('.gc-item-row').length > 1) {
    row.remove();
  }
}

function gcRecalcularTotalItem(row) {
  const qtd = parseFloat(row.querySelector('.gc-item-qtd')?.value || 0);
  const valorUnit = parseFloat(row.querySelector('.gc-item-valor')?.value || 0);
  const totalEl = row.querySelector('.gc-item-total');
  if (totalEl) {
    totalEl.textContent = (qtd * valorUnit).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  }
  gcRecalcularTotalGeral();
}

function gcRecalcularTotalGeral() {
  const totalGeralEl = document.getElementById('gc-total-geral');
  if (!totalGeralEl) return;
  let total = 0;
  document.querySelectorAll('.gc-item-row').forEach((row) => {
    const qtd = parseFloat(row.querySelector('.gc-item-qtd')?.value || 0);
    const valorUnit = parseFloat(row.querySelector('.gc-item-valor')?.value || 0);
    total += qtd * valorUnit;
  });
  totalGeralEl.textContent = total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

// =====================================================================
// Alerta / confirmação padrão do sistema (substitui alert() e confirm())
//   gcAlert({ type, title, message, confirmText })            -> Promise
//   gcConfirm({ type, title, message, confirmText, cancelText }) -> Promise<boolean>
// Tipos: 'success' | 'error' (ou 'danger') | 'warning' | 'info'.
// =====================================================================
function gcMetaTipoAlerta(tipo) {
  const mapa = {
    success: { icone: 'bi-check-circle-fill',       classe: 'gc-alert-success', botao: 'btn-success' },
    error:   { icone: 'bi-x-circle-fill',           classe: 'gc-alert-error',   botao: 'btn-danger' },
    danger:  { icone: 'bi-x-circle-fill',           classe: 'gc-alert-error',   botao: 'btn-danger' },
    warning: { icone: 'bi-exclamation-triangle-fill', classe: 'gc-alert-warning', botao: 'btn-warning' },
    info:    { icone: 'bi-info-circle-fill',        classe: 'gc-alert-info',    botao: 'btn-gc-primary' },
  };
  return mapa[tipo] || mapa.info;
}

function gcLerOpcoesConfirmacao(el) {
  return {
    type: el.dataset.gcConfirmType || 'warning',
    title: el.dataset.gcConfirmTitle || 'Confirmar ação',
    message: el.dataset.gcConfirmMessage || 'Tem certeza que deseja continuar?',
    confirmText: el.dataset.gcConfirmConfirm || 'Confirmar',
    cancelText: el.dataset.gcConfirmCancel || 'Cancelar',
  };
}

function gcExibirDialogo(opcoes) {
  const modo = opcoes.mode || 'alert';
  const el = document.getElementById('gc-alert-modal');

  // Defesa: se o markup do modal não existir (ex.: layout guest), cai para o
  // diálogo nativo apenas para não travar a ação — não deve ocorrer nas telas do app.
  if (!el || typeof bootstrap === 'undefined') {
    if (modo === 'confirm') {
      return Promise.resolve(window.confirm(opcoes.message || ''));
    }
    return Promise.resolve(true);
  }

  const meta = gcMetaTipoAlerta(opcoes.type);
  const content = el.querySelector('.gc-alert-content');
  const iconeEl = el.querySelector('[data-gc-alert-icon]');
  const tituloEl = el.querySelector('[data-gc-alert-title]');
  const mensagemEl = el.querySelector('[data-gc-alert-message]');
  const btnConfirmar = el.querySelector('[data-gc-alert-confirm]');
  const btnCancelar = el.querySelector('[data-gc-alert-cancel]');

  content.classList.remove('gc-alert-success', 'gc-alert-error', 'gc-alert-warning', 'gc-alert-info');
  content.classList.add(meta.classe);
  iconeEl.className = 'bi ' + meta.icone;
  tituloEl.textContent = opcoes.title || (modo === 'confirm' ? 'Confirmar ação' : 'Aviso');
  mensagemEl.textContent = opcoes.message || '';

  btnConfirmar.className = 'btn ' + meta.botao;
  btnConfirmar.textContent = opcoes.confirmText || (modo === 'confirm' ? 'Confirmar' : 'OK');
  btnCancelar.textContent = opcoes.cancelText || 'Cancelar';
  btnCancelar.classList.toggle('d-none', modo !== 'confirm');

  const instancia = bootstrap.Modal.getOrCreateInstance(el);

  return new Promise((resolve) => {
    let resultado = false;
    const aoConfirmar = () => { resultado = true; instancia.hide(); };
    const aoFechar = () => {
      btnConfirmar.removeEventListener('click', aoConfirmar);
      el.removeEventListener('hidden.bs.modal', aoFechar);
      resolve(resultado);
    };
    btnConfirmar.addEventListener('click', aoConfirmar);
    el.addEventListener('hidden.bs.modal', aoFechar);
    instancia.show();
  });
}

window.gcAlert = (opcoes = {}) => gcExibirDialogo(Object.assign({}, opcoes, { mode: 'alert' }));
window.gcConfirm = (opcoes = {}) => gcExibirDialogo(Object.assign({}, opcoes, { mode: 'confirm' }));

// =====================================================================
// Exportação de relatórios (PDF / Excel) com loading bloqueante e
// cancelamento REAL da requisição HTTP no cliente.
//
// Todos os botões de exportação usam <a data-gc-export> com o href apontando
// para a rota de download (mantendo filtros/query string). Em vez de navegar,
// interceptamos o clique e baixamos via fetch + AbortController + Blob:
//   - loading imediato e bloqueante (modal #gc-export-modal, backdrop estático);
//   - "Cancelar" chama controller.abort() -> aborta a requisição no navegador;
//   - clique repetido é ignorado enquanto houver geração em andamento;
//   - resposta HTML (login/erro) nunca é baixada como arquivo;
//   - nome do arquivo vem do Content-Disposition; URL temporária é revogada.
//
// Observação sobre o backend: o cancelamento interrompe a requisição no cliente.
// O PHP/DomPDF/Maatwebsite geram o arquivo em memória e só então o enviam; não
// há streaming incremental nem um ponto seguro de interrupção. O PHP pode, no
// máximo, encerrar o script ao detectar desconexão do cliente (ignore_user_abort
// desligado) ao tentar enviar a saída — mas isso não é garantido e não deixamos
// nenhum arquivo parcial, pois nada é persistido em disco. Portanto, não
// prometemos cancelar o processamento interno do servidor: garantimos o
// cancelamento efetivo da requisição do cliente, que é o requisito mínimo.
// =====================================================================
class GcExportError extends Error {
  constructor(message) {
    super(message);
    this.name = 'GcExportError';
    this.gcAmigavel = true; // mensagem já apropriada para exibição ao usuário
  }
}

function gcMensagemPorStatus(status) {
  switch (status) {
    case 401:
    case 419:
      return 'Sua sessão expirou. Atualize a página e faça login novamente.';
    case 403:
      return 'Você não tem permissão para gerar este relatório.';
    case 422:
      return 'Os filtros informados são inválidos. Revise e tente novamente.';
    case 404:
      return 'Relatório não encontrado.';
    default:
      if (status >= 500) {
        return 'O servidor encontrou um erro ao gerar o arquivo. Tente novamente em instantes.';
      }
      return 'Não foi possível gerar o arquivo (código ' + status + ').';
  }
}

function gcNomeArquivoDeResposta(resposta, fallback) {
  const cd = resposta.headers.get('Content-Disposition') || '';
  // Preferir filename*=UTF-8''... (RFC 5987), depois filename="...".
  let m = /filename\*=(?:UTF-8'')?([^;]+)/i.exec(cd);
  if (m && m[1]) {
    const bruto = m[1].trim().replace(/^"|"$/g, '');
    try { return decodeURIComponent(bruto); } catch (_) { return bruto; }
  }
  m = /filename="?([^";]+)"?/i.exec(cd);
  if (m && m[1]) {
    return m[1].trim();
  }
  return fallback;
}

function gcNomeArquivoFallback(link) {
  try {
    const url = new URL(link.href, window.location.origin);
    const segmentos = url.pathname.split('/').filter(Boolean);
    const tipo = (link.dataset.gcExportTipo || '').toLowerCase();
    const ext = tipo === 'excel' ? 'xlsx' : (tipo === 'pdf' ? 'pdf' : '');
    const base = segmentos.length ? segmentos.join('-') : 'relatorio';
    return ext ? base + '.' + ext : base;
  } catch (_) {
    return 'relatorio';
  }
}

function gcDispararDownload(blob, nome) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = nome || 'relatorio';
  document.body.appendChild(a);
  a.click();
  a.remove();
  // Revoga a URL temporária logo após o navegador capturar o download.
  // (Limpeza de recurso — não é atraso artificial de processamento.)
  setTimeout(() => URL.revokeObjectURL(url), 0);
}

function gcToast(mensagem, tipo) {
  const container = document.querySelector('.toast-container');
  if (!container || typeof bootstrap === 'undefined') return;

  const cores = {
    success: 'text-bg-success', error: 'text-bg-danger', danger: 'text-bg-danger',
    warning: 'text-bg-warning', info: 'text-bg-secondary',
  };
  const el = document.createElement('div');
  el.className = 'toast ' + (cores[tipo] || cores.info) + ' border-0';
  el.setAttribute('role', 'alert');

  const linha = document.createElement('div');
  linha.className = 'd-flex';
  const corpo = document.createElement('div');
  corpo.className = 'toast-body';
  corpo.textContent = mensagem; // texto controlado -> textContent evita HTML injetado
  const fechar = document.createElement('button');
  fechar.type = 'button';
  fechar.className = 'btn-close btn-close-white me-2 m-auto';
  fechar.setAttribute('data-bs-dismiss', 'toast');
  linha.appendChild(corpo);
  linha.appendChild(fechar);
  el.appendChild(linha);
  container.appendChild(el);

  const toast = new bootstrap.Toast(el, { delay: 4000 });
  el.addEventListener('hidden.bs.toast', () => el.remove());
  toast.show();
}

function gcInicializarExportacao() {
  const modalEl = document.getElementById('gc-export-modal');
  if (!modalEl || typeof bootstrap === 'undefined') return; // layout sem o modal

  const tituloEl = modalEl.querySelector('[data-gc-export-titulo]');
  const btnCancelar = modalEl.querySelector('[data-gc-export-cancelar]');
  let exportacaoAtual = null; // { controller, link } enquanto houver geração ativa

  const abrirModal = () => bootstrap.Modal.getOrCreateInstance(modalEl).show();
  const fecharModal = (aposFechar) => {
    const instancia = bootstrap.Modal.getOrCreateInstance(modalEl);
    if (typeof aposFechar === 'function') {
      modalEl.addEventListener('hidden.bs.modal', function once() {
        modalEl.removeEventListener('hidden.bs.modal', once);
        aposFechar();
      });
    }
    instancia.hide();
  };

  const restaurar = () => {
    if (exportacaoAtual && exportacaoAtual.link) {
      exportacaoAtual.link.classList.remove('disabled');
      exportacaoAtual.link.removeAttribute('aria-disabled');
    }
    exportacaoAtual = null;
  };

  async function iniciar(link) {
    const controller = new AbortController();
    exportacaoAtual = { controller, link };

    link.classList.add('disabled');
    link.setAttribute('aria-disabled', 'true');
    if (tituloEl) {
      tituloEl.textContent = 'Gerando ' + (link.dataset.gcExportTipo || 'arquivo') + '...';
    }
    abrirModal();

    try {
      const resposta = await fetch(link.href, {
        method: 'GET',
        credentials: 'same-origin', // envia cookies de sessão
        headers: {
          Accept: 'application/pdf, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/octet-stream, */*',
        },
        signal: controller.signal,
      });

      const contentType = (resposta.headers.get('Content-Type') || '').toLowerCase();

      if (!resposta.ok) {
        throw new GcExportError(gcMensagemPorStatus(resposta.status));
      }
      // Resposta HTML = redirecionamento p/ login ou página de erro do Laravel.
      // Nunca baixar como .pdf/.xlsx.
      if (contentType.includes('text/html')) {
        throw new GcExportError('Sua sessão pode ter expirado ou você não tem permissão para gerar este arquivo. Atualize a página e tente novamente.');
      }

      const blob = await resposta.blob();
      const nome = gcNomeArquivoDeResposta(resposta, gcNomeArquivoFallback(link));
      gcDispararDownload(blob, nome);

      fecharModal();
      restaurar();
    } catch (erro) {
      const cancelado = erro && erro.name === 'AbortError';
      fecharModal(() => {
        if (cancelado) {
          gcToast('Geração cancelada.', 'info');
        } else {
          const mensagem = erro && erro.gcAmigavel
            ? erro.message
            : 'Não foi possível gerar o arquivo. Verifique sua conexão e tente novamente.';
          if (erro && !erro.gcAmigavel) {
            console.error('Falha ao gerar relatório:', erro);
          }
          window.gcAlert({ type: 'error', title: 'Falha na exportação', message: mensagem });
        }
      });
      restaurar();
    }
  }

  btnCancelar.addEventListener('click', () => {
    if (exportacaoAtual) {
      exportacaoAtual.controller.abort();
    }
  });

  document.addEventListener('click', (e) => {
    const link = e.target.closest('[data-gc-export]');
    if (!link) return;
    e.preventDefault();
    if (exportacaoAtual) return; // já há uma geração em andamento -> ignora clique duplicado
    iniciar(link);
  });
}

// =====================================================================
// Enter nao envia formulario de DADOS
// =====================================================================
// Enviar com Enter e comportamento nativo do navegador, nao codigo nosso -
// por isso valia em todas as telas. Num cadastro de 25 campos, um Enter
// distraido salva a ficha pela metade.
//
// A regra sai do METODO do formulario, e nao de uma marcacao tela a tela:
//
//   POST -> entrada de dados. Enter NAO envia.
//   GET  -> filtro/busca. Enter envia: digitar um nome e apertar Enter e o
//           caminho esperado, e bloquear ali trocaria um problema por outro
//           - so que este apareceria dezenas de vezes por dia.
//
// Os dois grupos ja estavam separados assim no sistema inteiro (17 filtros
// em GET, 24 formularios de dados em POST), entao nenhuma view precisou
// mudar - e formulario novo ja nasce protegido.
//
// Excecao declarada: data-enter-envia. Hoje so o login usa.
document.addEventListener('keydown', (e) => {
  if (e.key !== 'Enter' || e.isComposing) return;

  const campo = e.target;
  if (!(campo instanceof HTMLElement)) return;

  // Enter no textarea quebra linha; nunca enviou nada.
  if (campo.tagName === 'TEXTAREA') return;

  // Foco no botao: a pessoa escolheu enviar. Sem esta saida, quem navega por
  // teclado ficaria sem forma nenhuma de enviar o formulario - um problema
  // de acessibilidade maior que o que estamos resolvendo.
  if (campo.tagName === 'BUTTON' || campo.tagName === 'A') return;
  if (campo.tagName === 'INPUT' && ['submit', 'button', 'reset'].includes(campo.type)) return;

  // .form antes de closest(): em /ponto/vinculos os selects ficam FORA do
  // <form> e se ligam a ele pelo atributo form="..." (<form> em volta de <tr>
  // e HTML invalido). So o closest() deixaria justamente esses de fora.
  const form = campo.form || campo.closest('form');
  if (!form) return;

  if (form.hasAttribute('data-enter-envia')) return;
  if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;

  e.preventDefault();
});
