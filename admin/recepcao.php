<?php
// admin/recepcao.php - Balcão & Recepção GymControl
// Requer autenticação do usuário (recepcionista / admin)
// require_once '../config/auth.php';
// require_once '../config/conexao.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recepção & Balcão - GymControl</title>
    <link rel="stylesheet" href="/style.css">
    <style>
        /* Inserir no topo da tag <style> de admin/recepcao.php */
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
}

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

.container {
  width: 100%;
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
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
}

.btn-primary { background-color: var(--cor-principal); color: #fff; }
.btn-accent { background-color: var(--cor-accent); color: #fff; }
.btn-outline { background: transparent; border-color: rgba(255, 255, 255, 0.2); color: #fff; }
.btn-block { width: 100%; }

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
}

.form-group input, .form-group select {
  padding: 10px 14px;
  border-radius: var(--radius);
  border: 1px solid #cbd5e1;
  background: #fff;
  font-size: 14px;
  outline: none;
}
        .recepcao-container {
            padding-top: 90px;
            padding-bottom: 40px;
        }

        .recepcao-grid {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 24px;
            margin-top: 24px;
        }

        .card-balcao {
            background: #ffffff;
            border: 1px solid var(--border-color, #e2e8f0);
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
            gap: 10px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 12px;
        }

        .search-box-checkin {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-box-checkin input {
            flex: 1;
            height: 48px;
            padding: 0 16px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 15px;
            outline: none;
        }

        .search-box-checkin input:focus {
            border-color: #0284c7;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        }

        .status-badge-catraca {
            padding: 16px;
            border-radius: 10px;
            text-align: center;
            font-weight: 800;
            font-size: 18px;
            margin-bottom: 20px;
            display: none;
        }

        .status-liberado {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }

        .status-bloqueado {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
        }

        .camera-box-balcao {
            width: 100%;
            aspect-ratio: 4 / 3;
            background: #090d16;
            border-radius: 12px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px dashed #94a3b8;
            margin-bottom: 16px;
        }

        #webcamRecepcao {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .face-guide {
            position: absolute;
            width: 55%;
            height: 70%;
            border: 2px dashed rgba(56, 189, 248, 0.8);
            border-radius: 50%;
            pointer-events: none;
            box-shadow: 0 0 0 9999px rgba(9, 13, 22, 0.45);
        }

        .camera-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .recentes-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .item-recente {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #f1f5f9;
        }

        .item-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #0284c7;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        @media (max-width: 992px) {
            .recepcao-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <header class="navbar">
        <div class="container">
            <div class="nav-wrapper">
                <a href="#" class="logo">
                    <div class="logo-badge">GC</div>
                    <span>GymControl <small style="font-size: 12px; color: #38bdf8; font-weight: 500;">| Balcão & Recepção</small></span>
                </a>
                <div class="nav-cta">
                    <a href="../logout.php" class="btn btn-outline" style="padding: 6px 14px; font-size: 13px;">Sair</a>
                </div>
            </div>
        </div>
    </header>

    <main class="container recepcao-container">
        
        <h2>Painel do Atendente</h2>
        <p style="color: var(--text-muted); margin-bottom: 20px;">Gerencie o acesso de alunos, realize novas matrículas e registre a biometria facial para a catraca.</p>

        <!-- Status de Validação da Catraca -->
        <div id="statusCatraca" class="status-badge-catraca status-liberado">
            ✓ ACESSO LIBERADO - Rodrigo Nogueira (Plano Mensal - Adimplente)
        </div>

        <div class="recepcao-grid">
            
            <!-- ESQUERDA: CADASTRO E MATRÍCULA -->
            <div class="card-balcao">
                <div class="card-header-title">
                    <span>👤</span> Nova Matrícula / Cadastro de Aluno
                </div>

                <form id="formMatricula" onsubmit="salvarMatricula(event)">
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="nomeAluno">Nome Completo do Aluno *</label>
                        <input type="text" id="nomeAluno" required placeholder="Ex: Carlos Eduardo Silva">
                    </div>

                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                        <div class="form-group">
                            <label for="cpfAluno">CPF *</label>
                            <input type="text" id="cpfAluno" required placeholder="000.000.000-00">
                        </div>

                        <div class="form-group">
                            <label for="telefoneAluno">WhatsApp *</label>
                            <input type="tel" id="telefoneAluno" required placeholder="(21) 99999-9999">
                        </div>
                    </div>

                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                        <div class="form-group">
                            <label for="planoMatricula">Plano Interno *</label>
                            <select id="planoMatricula" required>
                                <option value="Mensal">Plano Mensal - R$ 119,90</option>
                                <option value="Semestral">Plano Semestral - R$ 99,90/mês</option>
                                <option value="Anual VIP">Plano Anual VIP - R$ 79,90/mês</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="formaPagamento">Forma de Pagamento *</label>
                            <select id="formaPagamento" required>
                                <option value="Cartao">Cartão de Crédito / Débito</option>
                                <option value="PIX">PIX (Instantâneo)</option>
                                <option value="Dinheiro">Dinheiro no Balcão</option>
                            </select>
                        </div>
                    </div>

                    <!-- WIDGET DE CÂMERA BIOMÉTRICA -->
                    <div style="background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 16px;">
                        <label style="font-size: 13px; font-weight: 700; color: #0284c7; display: block; margin-bottom: 8px;">
                            📷 Biometria Facial para Catraca
                        </label>
                        
                        <div class="camera-box-balcao">
                            <video id="webcamRecepcao" autoplay playsinline></video>
                            <div class="face-guide"></div>
                            <canvas id="canvasSnap" style="display:none;"></canvas>
                        </div>

                        <div class="camera-actions">
                            <button type="button" class="btn btn-outline" style="color: #0284c7; border-color: #0284c7; font-size: 13px;" onclick="iniciarCamRecepcao()">Ligar Câmera</button>
                            <button type="button" class="btn btn-primary" style="font-size: 13px;" onclick="tirarFotoRecepcao()">Capturar Foto</button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-accent btn-block" style="height: 48px; font-size: 15px;">
                        Finalizar Cadastro & Liberar Catraca
                    </button>
                </form>
            </div>

            <!-- DIREITA: VALIDAÇÃO & FEED -->
            <div style="display: flex; flex-direction: column; gap: 24px;">
                
                <div class="card-balcao">
                    <div class="card-header-title">
                        <span>🔍</span> Validação Manual de Acesso / Consulta
                    </div>

                    <div class="search-box-checkin">
                        <input type="text" id="inputBusca" placeholder="Digite CPF, Nome ou Matrícula...">
                        <button class="btn btn-primary" onclick="buscarAlunoBalcao()">Validar</button>
                    </div>

                    <p style="font-size: 13px; color: #64748b;">Digite o CPF do aluno para consultar a adimplência financeira e o status de acesso na catraca.</p>
                </div>

                <div class="card-balcao">
                    <div class="card-header-title">
                        <span>🚪</span> Últimos Acessos Registrados na Catraca
                    </div>

                    <ul class="recentes-list" id="listaAcessos">
                        <li class="item-recente">
                            <div class="item-info">
                                <div class="avatar-placeholder">RN</div>
                                <div>
                                    <strong style="font-size: 14px; display: block;">Rodrigo Nogueira</strong>
                                    <small style="color: #64748b;">Reconhecimento Facial • Hoje às 13:22</small>
                                </div>
                            </div>
                            <span style="background: #dcfce7; color: #15803d; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 700;">Liberado</span>
                        </li>

                        <li class="item-recente">
                            <div class="item-info">
                                <div class="avatar-placeholder" style="background: #64748b;">MS</div>
                                <div>
                                    <strong style="font-size: 14px; display: block;">Mariana Souza</strong>
                                    <small style="color: #64748b;">Busca por CPF • Hoje às 13:10</small>
                                </div>
                            </div>
                            <span style="background: #dcfce7; color: #15803d; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 700;">Liberado</span>
                        </li>

                        <li class="item-recente">
                            <div class="item-info">
                                <div class="avatar-placeholder" style="background: #ef4444;">FA</div>
                                <div>
                                    <strong style="font-size: 14px; display: block;">Felipe Alves</strong>
                                    <small style="color: #64748b;">Reconhecimento Facial • Hoje às 12:45</small>
                                </div>
                            </div>
                            <span style="background: #fee2e2; color: #b91c1c; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 700;">Bloqueado</span>
                        </li>
                    </ul>
                </div>

            </div>

        </div>

    </main>

    <script>
        let streamRecepcao = null;

        async function iniciarCamRecepcao() {
            try {
                const video = document.getElementById('webcamRecepcao');
                streamRecepcao = await navigator.mediaDevices.getUserMedia({ video: true });
                video.srcObject = streamRecepcao;
            } catch (err) {
                alert('Não foi possível acessar a câmera do balcão: ' + err.message);
            }
        }

        function tirarFotoRecepcao() {
            const video = document.getElementById('webcamRecepcao');
            const canvas = document.getElementById('canvasSnap');
            if (video && video.srcObject) {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                alert('Foto biométrica capturada com sucesso!');
            } else {
                alert('Ligue a câmera antes de capturar a foto.');
            }
        }

        function buscarAlunoBalcao() {
            const termo = document.getElementById('inputBusca').value;
            const statusBox = document.getElementById('statusCatraca');
            if (!termo) {
                alert('Digite um nome ou CPF para buscar.');
                return;
            }
            statusBox.style.display = 'block';
            statusBox.className = 'status-badge-catraca status-liberado';
            statusBox.innerHTML = `✓ ACESSO LIBERADO - ${termo} (Plano Ativo - Financeiro OK)`;
        }

        function salvarMatricula(e) {
            e.preventDefault();
            const nome = document.getElementById('nomeAluno').value;
            alert(`Matrícula de ${nome} realizada com sucesso! Cadastro e biometria salvos.`);
            document.getElementById('formMatricula').reset();
        }
    </script>
</body>
</html>