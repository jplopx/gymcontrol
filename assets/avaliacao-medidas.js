/* Máscaras de medidas: formatação visual brasileira; servidor valida limites e unidades. */
(() => {
  'use strict';
  const campos = [...document.querySelectorAll('input[data-medida]')];
  if (!campos.length) return;
  const normalizar = (value) => {
    const limpo = String(value).replace(/\./g, ',').replace(/[^\d,]/g, '');
    const [inteiro, ...decimais] = limpo.split(',');
    return inteiro + (decimais.length ? ',' + decimais.join('').slice(0, 2) : '');
  };
  const numero = (input) => Number.parseFloat((input.value || '').replace(',', '.'));
  const peso = document.getElementById('peso');
  const altura = document.getElementById('altura');
  const saida = document.getElementById('avaliacao-imc');
  function calcular() {
    if (!peso || !altura || !saida) return;
    const p = numero(peso), a = numero(altura);
    saida.textContent = Number.isFinite(p) && Number.isFinite(a) && p > 0 && a > 0
      ? 'IMC: ' + (p / (a * a)).toFixed(1).replace('.', ',')
      : 'IMC: informe peso e altura';
  }
  campos.forEach(input => {
    input.addEventListener('input', () => {
      const antes = input.value;
      const apos = normalizar(antes);
      if (antes !== apos) {
        const pos = input.selectionStart ?? antes.length;
        input.value = apos;
        input.setSelectionRange(Math.min(pos, apos.length), Math.min(pos, apos.length));
      }
      calcular();
    });
    input.addEventListener('blur', () => {
      if (input.value.endsWith(',')) input.value = input.value.slice(0, -1);
      calcular();
    });
  });
  calcular();
})();
