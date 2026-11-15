<?php
// admin/loja.php - Ponto de Venda (PDV) / Loja da Academia GymControl
require_once __DIR__ . '/../config/auth.php';
verificarAcesso(['loja', 'admin']);
require_once __DIR__ . '/../config/conexao.php';

// Dados do funcionário logado
$funcionario_logado = [
    'id'    => $_SESSION['usuario_id'] ?? 0,
    'nome'  => $_SESSION['usuario_nome'] ?? 'Colaborador Loja',
    'cargo' => (($_SESSION['usuario_nivel'] ?? '') === 'admin') ? 'Administrador Geral' : 'Operador de Loja / PDV'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loja & PDV - GymControl</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ==========================================
           1. ESTILOS BASE E RESET (INLINE FIX)
           ========================================== */
        :root {
            --cor-principal: #0284c7;
            --cor-principal-hover: #0369a1;
            --cor-secundaria: #0f172a;
            --cor-accent: #10b981;
            --bg-light: #f8fafc;
            --bg-surface: #ffffff;
            --border-color: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --radius: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-main);
            line-height: 1.6;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ==========================================
           2. NAVBAR FIXA
           ========================================== */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 72px;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(8px);
            z-index: 1000;
            display: flex;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .nav-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            font-size: 20px;
            color: #fff;
            text-decoration: none;
        }

        .logo-badge {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, var(--cor-principal), #38bdf8);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #fff;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 14px;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-outline {
            background: transparent;
            border-color: rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: #fff;
        }

        .btn-accent {
            background-color: var(--cor-accent);
            color: #fff;
        }

        .btn-accent:hover {
            background-color: #059669;
        }

        .btn-block {
            width: 100%;
        }

        /* ==========================================
           3. LAYOUT DO PDV / LOJA
           ========================================== */
        .loja-container {
            padding-top: 96px;
            padding-bottom: 40px;
        }

        .loja-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 24px;
            margin-top: 20px;
        }

        .card-pdv {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .card-header-title {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 12px;
        }

        .produtos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .produto-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .produto-card:hover {
            border-color: var(--cor-principal);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.15);
        }

        .produto-card h4 {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .produto-card .preco {
            font-size: 16px;
            font-weight: 800;
            color: var(--cor-principal);
            margin-bottom: 8px;
        }

        .produto-card .estoque {
            font-size: 11px;
            color: var(--text-muted);
        }

        /* Resumo do Carrinho */
        .badge-operador {
            background: rgba(2, 132, 199, 0.1);
            color: var(--cor-principal);
            border: 1px solid rgba(2, 132, 199, 0.2);
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .carrinho-itens {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 200px;
            overflow-y: auto;
            border-top: 1px solid #f1f5f9;
            border-bottom: 1px solid #f1f5f9;
            padding: 12px 0;
        }

        .item-carrinho {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 14px;
        }

        .item-carrinho .qtd-btn {
            background: #e2e8f0;
            border: none;
            width: 24px;
            height: 24px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 700;
        }

        .carrinho-total {
            background: #0f172a;
            color: #fff;
            padding: 16px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .carrinho-total span {
            font-size: 22px;
            font-weight: 800;
            color: #38bdf8;
        }

        @media (max-width: 992px) {
            .loja-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <!-- Header fixo -->
    <header class="navbar">
        <div class="container">
            <div class="nav-wrapper">
                <a href="../index.php" class="logo">
                    <div class="logo-badge">GC</div>
                    <span>GymControl <small style="font-size: 12px; color: #38bdf8; font-weight: 500;">| Loja & PDV</small></span>
                </a>
                <div class="nav-cta" style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 13px; color: #94a3b8;">
                        Operador: <strong style="color: #fff;"><?php echo htmlspecialchars($funcionario_logado['nome']); ?></strong>
                        <span style="background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; margin-left: 6px;">
                            <?php echo htmlspecialchars($_SESSION['usuario_nivel'] ?? 'Loja'); ?>
                        </span>
                    </span>
                    <?php if (($_SESSION['usuario_nivel'] ?? '') === 'admin'): ?>
                        <a href="index.php" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;">Painel Geral</a>
                        <a href="recepcao.php" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;">← Recepção / Balcão</a>
                    <?php endif; ?>
                    <a href="../logout.php" class="btn btn-outline" style="padding: 6px 14px; font-size: 13px;">
                        <i class="fa-solid fa-right-from-bracket"></i> Sair
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container loja-container">
        <?php renderizarAvisoPermissao(); ?>
        <h2>Ponto de Venda (PDV da Academia)</h2>
        <p style="color: var(--text-muted); margin-bottom: 20px;">Registre vendas de suplementos, bebidas e acessórios vinculando o aluno e o funcionário responsável.</p>

        <div class="loja-grid">
            
            <!-- ESQUERDA: PRODUTOS -->
            <div class="card-pdv">
                <div class="card-header-title">
                    <span>📦 Produtos Cadastrados</span>
                </div>

                <div class="produtos-grid">
                    <div class="produto-card" onclick="adicionarAoCarrinho('Whey Protein 900g', 120.00)">
                        <div>
                            <h4>Whey Protein 900g</h4>
                            <div class="preco">R$ 120,00</div>
                        </div>
                        <div class="estoque">Estoque: 14 un.</div>
                    </div>

                    <div class="produto-card" onclick="adicionarAoCarrinho('Creatina 250g', 89.90)">
                        <div>
                            <h4>Creatina 250g</h4>
                            <div class="preco">R$ 89,90</div>
                        </div>
                        <div class="estoque">Estoque: 8 un.</div>
                    </div>

                    <div class="produto-card" onclick="adicionarAoCarrinho('Energético 473ml', 12.00)">
                        <div>
                            <h4>Energético 473ml</h4>
                            <div class="preco">R$ 12,00</div>
                        </div>
                        <div class="estoque">Estoque: 45 un.</div>
                    </div>

                    <div class="produto-card" onclick="adicionarAoCarrinho('Água Mineral 500ml', 4.00)">
                        <div>
                            <h4>Água Mineral 500ml</h4>
                            <div class="preco">R$ 4,00</div>
                        </div>
                        <div class="estoque">Estoque: 120 un.</div>
                    </div>

                    <div class="produto-card" onclick="adicionarAoCarrinho('Camisa Dry-Fit', 59.90)">
                        <div>
                            <h4>Camisa Dry-Fit</h4>
                            <div class="preco">R$ 59,90</div>
                        </div>
                        <div class="estoque">Estoque: 5 un.</div>
                    </div>
                </div>
            </div>

            <!-- DIREITA: RESUMO E REGISTRO DA VENDA -->
            <div class="card-pdv" style="display: flex; flex-direction: column; gap: 16px;">
                <div class="card-header-title">
                    <span>🛒 Resumo da Venda</span>
                </div>

                <!-- VENDEDOR -->
                <div class="badge-operador">
                    <span>👨‍💼 Vendedor:</span>
                    <strong><?php echo $funcionario_logado['nome']; ?> (ID #<?php echo $funcionario_logado['id']; ?>)</strong>
                </div>

                <!-- ALUNO -->
                <div class="form-group">
                    <label for="alunoComprador">Aluno Comprador *</label>
                    <select id="alunoComprador" style="padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 14px;">
                        <option value="">-- Selecione o Aluno --</option>
                        <option value="101">Rodrigo Nogueira (CPF: 111.222.333-44)</option>
                        <option value="102">Mariana Souza (CPF: 555.666.777-88)</option>
                        <option value="0">Cliente Visitante (Venda Avulsa)</option>
                    </select>
                </div>

                <label style="font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Itens Selecionados</label>
                <ul class="carrinho-itens" id="listaCarrinho">
                    <li style="color: #94a3b8; font-size: 13px; text-align: center; padding: 20px 0;">Nenhum produto selecionado. Clique nos produtos ao lado para adicionar.</li>
                </ul>

                <div class="carrinho-total">
                    <small>Total a Pagar:</small>
                    <span id="valorTotal">R$ 0,00</span>
                </div>

                <div class="form-group">
                    <label for="pagamentoLoja">Forma de Pagamento</label>
                    <select id="pagamentoLoja" style="padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 14px;">
                        <option value="PIX">PIX (Instantâneo)</option>
                        <option value="Cartao_Debito">Cartão de Débito</option>
                        <option value="Cartao_Credito">Cartão de Crédito</option>
                        <option value="Dinheiro">Dinheiro em Espécie</option>
                        <option value="Conta_Aluno">Debitar na Fatura do Aluno</option>
                    </select>
                </div>

                <button class="btn btn-accent btn-block" style="height: 48px; font-size: 15px;" onclick="finalizarVendaLoja()">
                    ✓ Confirmar & Registrar Venda
                </button>
            </div>

        </div>
    </main>

    <script>
        let carrinho = [];

        function adicionarAoCarrinho(nome, preco) {
            const itemExistente = carrinho.find(i => i.nome === nome);
            if (itemExistente) {
                itemExistente.qtd++;
            } else {
                carrinho.push({ nome, preco, qtd: 1 });
            }
            renderizarCarrinho();
        }

        function alterarQtd(index, delta) {
            carrinho[index].qtd += delta;
            if (carrinho[index].qtd <= 0) {
                carrinho.splice(index, 1);
            }
            renderizarCarrinho();
        }

        function renderizarCarrinho() {
            const lista = document.getElementById('listaCarrinho');
            const totalEl = document.getElementById('valorTotal');
            
            if (carrinho.length === 0) {
                lista.innerHTML = '<li style="color: #94a3b8; font-size: 13px; text-align: center; padding: 20px 0;">Nenhum produto selecionado. Clique nos produtos ao lado para adicionar.</li>';
                totalEl.innerText = 'R$ 0,00';
                return;
            }

            let total = 0;
            lista.innerHTML = '';

            carrinho.forEach((item, idx) => {
                const subtotal = item.preco * item.qtd;
                total += subtotal;

                const li = document.createElement('li');
                li.className = 'item-carrinho';
                li.innerHTML = `
                    <div>
                        <strong>${item.nome}</strong><br>
                        <small style="color: #64748b;">R$ ${item.preco.toFixed(2)} x ${item.qtd}</small>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <button class="qtd-btn" onclick="alterarQtd(${idx}, -1)">-</button>
                        <span>${item.qtd}</span>
                        <button class="qtd-btn" onclick="alterarQtd(${idx}, 1)">+</button>
                    </div>
                `;
                lista.appendChild(li);
            });

            totalEl.innerText = `R$ ${total.toFixed(2)}`;
        }

        function finalizarVendaLoja() {
            const selectAluno = document.getElementById('alunoComprador');
            const alunoId = selectAluno.value;
            const alunoNome = selectAluno.options[selectAluno.selectedIndex].text;
            const formaPgto = document.getElementById('pagamentoLoja').value;

            if (carrinho.length === 0) {
                alert('Adicione produtos ao carrinho antes de finalizar.');
                return;
            }

            if (!alunoId) {
                alert('Selecione o aluno comprador.');
                return;
            }

            alert(`✅ VENDA REGISTRADA COM SUCESSO!\n\n` +
                  ` • Vendedor (Funcionário): Nicolas Tavares (ID #4)\n` +
                  ` • Comprador (Aluno): ${alunoNome}\n` +
                  ` • Pagamento: ${formaPgto}\n` +
                  ` • Total: ${document.getElementById('valorTotal').innerText}`);

            carrinho = [];
            renderizarCarrinho();
            selectAluno.value = '';
        }
    </script>
</body>
</html>