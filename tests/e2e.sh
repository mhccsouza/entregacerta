B=http://127.0.0.1:8099
tok(){ grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login(){ J=$1; T=$(curl -s -c $J $B/login.php | tok); curl -s -b $J -c $J -o /dev/null -w "login $2: %{http_code} -> %{redirect_url}\n" -d "csrf=$T&login=$2&senha=$3" $B/login.php; }
login /tmp/c1 cris senha12345; login /tmp/c2 dep senha12345; login /tmp/c3 emerson senha12345; login /tmp/bad cris errada
D=$(date -d "+1 day" +%F)
T=$(curl -s -b /tmp/c1 $B/pedido_novo.php | tok)
curl -s -b /tmp/c1 -o /dev/null -w "pedido 1: %{http_code} %{redirect_url}\n" --data-urlencode "csrf=$T" --data-urlencode "nome=Cliente Teste" --data-urlencode "telefone=71999990000" --data-urlencode "endereco=Rua Ficticia 10" --data-urlencode "bairro=Retiro" --data-urlencode "vendedor=Ione" --data-urlencode "data_entrega=$D" --data-urlencode "qtd[]=4" --data-urlencode "un[]=cx" --data-urlencode "desc[]=Piso 58120 A" --data-urlencode "qtd[]=2,5" --data-urlencode "un[]=sc" --data-urlencode "desc[]=Arg. ACII" $B/pedido_novo.php
T=$(curl -s -b /tmp/c1 $B/pedido_novo.php | tok)
curl -s -b /tmp/c1 -o /dev/null -w "pedido 2: %{http_code}\n" --data-urlencode "csrf=$T" --data-urlencode "nome=Outro Cliente" --data-urlencode "endereco=Av. Teste 5" --data-urlencode "bairro=Pernambues" --data-urlencode "vendedor=Bruno" --data-urlencode "data_entrega=$D" --data-urlencode "qtd[]=1" --data-urlencode "un[]=un" --data-urlencode "desc[]=Nivelador" $B/pedido_novo.php
echo "-- validacao sem itens:"; T=$(curl -s -b /tmp/c1 $B/pedido_novo.php | tok)
curl -s -b /tmp/c1 --data-urlencode "csrf=$T" --data-urlencode "nome=X" $B/pedido_novo.php | grep -o 'role="alert">[^<]*'
echo "-- CSRF invalido:"; curl -s -b /tmp/c1 -o /dev/null -w "%{http_code}\n" -d "csrf=zzz&nome=x" $B/pedido_novo.php
echo "-- perfis (balcao->separacao, deposito->pedido_novo; esperado 403 403):"; curl -s -b /tmp/c1 -o /dev/null -w "%{http_code} " $B/separacao.php; curl -s -b /tmp/c2 -o /dev/null -w "%{http_code}\n" $B/pedido_novo.php
echo "-- sem login:"; curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" $B/pedidos.php
echo "-- XSS escapado:"; T=$(curl -s -b /tmp/c1 $B/pedido_novo.php | tok)
curl -s -b /tmp/c1 -o /dev/null --data-urlencode "csrf=$T" --data-urlencode "nome=<script>alert(1)</script>" --data-urlencode "endereco=x" --data-urlencode "vendedor=Ione" --data-urlencode "data_entrega=$D" --data-urlencode "qtd[]=1" --data-urlencode "un[]=un" --data-urlencode "desc[]=teste" $B/pedido_novo.php
curl -s -b /tmp/c1 "$B/pedidos.php?data=$D" | grep -c '<script>alert' 
echo "-- separacao (pedido 1 completo, pedido 2 sem marcar):"
curl -s -b /tmp/c2 "$B/separacao.php?data=$D" > /tmp/sep.html; T=$(tok </tmp/sep.html)
curl -s -b /tmp/c2 -o /dev/null --data "csrf=$T&data=$D&sep[]=1&sep[]=2" $B/separacao.php
php tests/sql.php -q "select 'pedido '||id||' '||status as s from pedidos" 
echo "-- rota (emerson monta com pedidos 2 e 1, ordem 1 e 2):"
curl -s -b /tmp/c3 "$B/rota.php?data=$D" > /tmp/rota.html; T=$(tok </tmp/rota.html)
curl -s -b /tmp/c3 -o /dev/null --data "csrf=$T&data=$D&pedido[]=1&pedido[]=2&ordem[2]=1&ordem[1]=2" $B/rota.php
php tests/sql.php -q "select 'pedido '||id||' '||status||' rota '||rota_id||' ordem '||ordem_rota as s from pedidos where rota_id is not null"
curl -s -b /tmp/c3 "$B/rota.php?data=$D" | grep -o '<strong>[0-9]\. [^<]*' 
echo "-- entrega pedido 1 conferindo so o item 1 (item 2 vira pendencia):"
curl -s -b /tmp/c3 "$B/entrega.php?id=1" > /tmp/ent.html; T=$(tok </tmp/ent.html)
curl -s -b /tmp/c3 -o /dev/null -w "%{http_code} %{redirect_url}\n" --data "csrf=$T&id=1&ok[]=1&obs=faltou argamassa" $B/entrega.php
php tests/sql.php -q "select 'pedido '||id||' '||status as s from pedidos where id=1"
curl -s -b /tmp/c3 $B/pendencias.php | grep -o 'Falta: [^<]*'
echo "-- resolver pendencia:"
curl -s -b /tmp/c3 $B/pendencias.php > /tmp/pen.html; T=$(tok </tmp/pen.html)
curl -s -b /tmp/c3 -o /dev/null --data "csrf=$T&id=1&pedido_id=1" $B/pendencias.php
php tests/sql.php -q "select 'pedido '||id||' '||status as s from pedidos where id=1"
echo "-- painel:"; curl -s -b /tmp/c3 $B/index.php | grep -o '<strong class="num">[0-9]*</strong><span>[^<]*' | sed 's/<[^>]*>/ /g'
echo "-- EDITAR / EXCLUIR (balcao):"
D2=$(date -d "+2 day" +%F)
T=$(curl -s -b /tmp/c1 $B/pedido_novo.php | tok)
curl -s -b /tmp/c1 -o /dev/null --data-urlencode "csrf=$T" --data-urlencode "nome=Cliente Editar" --data-urlencode "endereco=Rua Errada 1" --data-urlencode "vendedor=Ione" --data-urlencode "data_entrega=$D2" --data-urlencode "qtd[]=1" --data-urlencode "un[]=un" --data-urlencode "desc[]=Item errado" $B/pedido_novo.php
NID=$(php tests/sql.php -q "select max(id) as m from pedidos")
curl -s -b /tmp/c1 "$B/pedido_novo.php?id=$NID" | grep -o 'value="Rua Errada 1"\|Editar pedido #[0-9]*\|value="Item errado"' | sort -u
T=$(curl -s -b /tmp/c1 "$B/pedido_novo.php?id=$NID" | tok)
curl -s -b /tmp/c1 -o /dev/null -w "salvar edicao: %{http_code}\n" --data-urlencode "csrf=$T" --data-urlencode "id=$NID" --data-urlencode "nome=Cliente Editar" --data-urlencode "endereco=Rua Certa 99" --data-urlencode "vendedor=Bruno" --data-urlencode "data_entrega=$D2" --data-urlencode "qtd[]=3" --data-urlencode "un[]=cx" --data-urlencode "desc[]=Piso certo" $B/pedido_novo.php
php tests/sql.php -q "select 'pedido '||p.id||' '||c.endereco||' '||p.vendedor||' itens='||(select count(*) from itens_pedido i where i.pedido_id=p.id)||' '||(select descricao from itens_pedido i where i.pedido_id=p.id) as s from pedidos p join clientes c on c.id=p.cliente_id where p.id=$NID"
echo "pedido 2 (ja em rota) nao pode editar:"; curl -s -b /tmp/c1 "$B/pedido_novo.php?id=2" | grep -o 'não pode mais ser editado'
echo "balcao ve botao Excluir:"; curl -s -b /tmp/c1 "$B/pedidos.php?data=$D2" | grep -c 'pedido_excluir.php'
T=$(curl -s -b /tmp/c1 "$B/pedidos.php?data=$D2" | tok)
curl -s -b /tmp/c1 -o /dev/null -w "excluir: %{http_code} %{redirect_url}\n" -d "csrf=$T&id=$NID" $B/pedido_excluir.php
php tests/sql.php -q "select 'restam '||count(*)||' pedido(s) com id=$NID; itens: '||(select count(*) from itens_pedido where pedido_id=$NID) as s from pedidos where id=$NID"
echo "excluir pedido ja em rota (2) -> bloqueado:"; curl -s -b /tmp/c1 -o /dev/null -w "%{redirect_url}\n" -d "csrf=$T&id=2" $B/pedido_excluir.php
echo "deposito tenta excluir (403):"; curl -s -b /tmp/c2 -o /dev/null -w "%{http_code}\n" -d "csrf=x&id=1" $B/pedido_excluir.php
echo "-- CANCELAR (admin) pedido ja separado:"
login /tmp/ad admin senha12345
D3=$(date -d "+4 day" +%F)
T=$(curl -s -b /tmp/c1 $B/pedido_novo.php | tok)
curl -s -b /tmp/c1 -o /dev/null --data-urlencode "csrf=$T" --data-urlencode "nome=Desistiu" --data-urlencode "endereco=Rua D" --data-urlencode "vendedor=Ione" --data-urlencode "data_entrega=$D3" --data-urlencode "qtd[]=2" --data-urlencode "un[]=cx" --data-urlencode "desc[]=Piso X" $B/pedido_novo.php
CID=$(php tests/sql.php -q "select max(id) as m from pedidos")
curl -s -b /tmp/c2 "$B/separacao.php?data=$D3" > /tmp/s.html; T=$(tok </tmp/s.html); IT=$(php tests/sql.php -q "select max(id) as m from itens_pedido")
curl -s -b /tmp/c2 -o /dev/null --data "csrf=$T&data=$D3&sep[]=$IT" $B/separacao.php
php tests/sql.php -q "select 'antes: pedido $CID '||status as s from pedidos where id=$CID"
echo "balcao tenta cancelar (403):"; curl -s -b /tmp/c1 -o /dev/null -w "%{http_code}\n" -d "csrf=x&id=$CID&motivo=x" $B/pedido_cancelar.php
echo "balcao NAO ve campo de cancelar:"; curl -s -b /tmp/c1 "$B/pedidos.php?data=$D3" | grep -c 'pedido_cancelar.php'
echo "admin ve:"; curl -s -b /tmp/ad "$B/pedidos.php?data=$D3" > /tmp/p.html; grep -c 'pedido_cancelar.php' /tmp/p.html; T=$(tok </tmp/p.html)
echo "sem motivo:"; curl -s -b /tmp/ad -o /dev/null -w "%{redirect_url}\n" -d "csrf=$T&id=$CID&motivo=" $B/pedido_cancelar.php
curl -s -b /tmp/ad -o /dev/null -w "cancelar: %{redirect_url}\n" --data-urlencode "csrf=$T" -d "id=$CID" --data-urlencode "motivo=Cliente desistiu da compra" $B/pedido_cancelar.php
php tests/sql.php -q "select 'depois: pedido $CID '||status||' | '||observacao as s from pedidos where id=$CID"
echo "some da separacao:"; curl -s -b /tmp/c2 "$B/separacao.php?data=$D3" | grep -c "Pedido #$CID"
echo "some da lista geral:"; curl -s -b /tmp/c1 "$B/pedidos.php" | grep -c "#$CID ·"
echo "entregue nao cancela:"; curl -s -b /tmp/ad -o /dev/null -w "%{redirect_url}\n" -d "csrf=$T&id=1&motivo=x" $B/pedido_cancelar.php
echo "-- ADMIN EXCLUI QUALQUER PEDIDO (limpeza de testes):"
D4=$(date -d "+5 day" +%F)
T=$(curl -s -b /tmp/c1 $B/pedido_novo.php | tok)
curl -s -b /tmp/c1 -o /dev/null --data-urlencode "csrf=$T" --data-urlencode "nome=Teste Limpeza" --data-urlencode "telefone=71900000099" --data-urlencode "endereco=Rua Lixo" --data-urlencode "vendedor=Ione" --data-urlencode "data_entrega=$D4" --data-urlencode "qtd[]=1" --data-urlencode "un[]=un" --data-urlencode "desc[]=Item teste" $B/pedido_novo.php
LID=$(php tests/sql.php -q "select max(id) as m from pedidos")
curl -s -b /tmp/c3 "$B/rota.php?data=$D4" > /tmp/r.html; T=$(tok </tmp/r.html)
curl -s -b /tmp/c3 -o /dev/null --data "csrf=$T&data=$D4&pedido[]=$LID&ordem[$LID]=1" $B/rota.php
php tests/sql.php -q "select 'pedido $LID '||status||' rota '||coalesce(rota_id,'-') as s from pedidos where id=$LID"
T=$(tok < <(curl -s -b /tmp/c1 $B/pedidos.php))
echo "balcao tenta excluir pedido em rota (bloqueado):"; curl -s -b /tmp/c1 -o /dev/null -w "%{redirect_url}\n" -d "csrf=$T&id=$LID" $B/pedido_excluir.php
echo "admin ve botao definitivo:"; curl -s -b /tmp/ad "$B/pedidos.php?data=$D4" > /tmp/p.html; grep -c 'Excluir definitivamente' /tmp/p.html; T=$(tok </tmp/p.html)
curl -s -b /tmp/ad -o /dev/null -w "admin exclui: %{redirect_url}\n" -d "csrf=$T&id=$LID" $B/pedido_excluir.php
php tests/sql.php -q "select 'pedido='||(select count(*) from pedidos where id=$LID)||' itens='||(select count(*) from itens_pedido where pedido_id=$LID)||' clientes teste='||(select count(*) from clientes where nome='Teste Limpeza')||' rotas vazias='||(select count(*) from rotas where id not in (select rota_id from pedidos where rota_id is not null)) as s"
echo "-- DATA A COMBINAR:"
T=$(curl -s -b /tmp/c1 $B/pedido_novo.php | tok)
curl -s -b /tmp/c1 -o /dev/null -w "criar a combinar: %{redirect_url}\n" --data-urlencode "csrf=$T" --data-urlencode "nome=Cliente Prazo Aberto" --data-urlencode "endereco=Rua Calma 3" --data-urlencode "vendedor=Bruno" --data-urlencode "a_combinar=1" --data-urlencode "qtd[]=2" --data-urlencode "un[]=cx" --data-urlencode "desc[]=Piso Z" $B/pedido_novo.php
php tests/sql.php -q "select 'data gravada: '||data_entrega as s from pedidos order by id desc limit 1"
echo "aparece em 'a combinar':"; curl -s -b /tmp/c1 "$B/pedidos.php?status=a_combinar" | grep -o 'Cliente Prazo Aberto' | head -1
echo "painel conta a combinar:"; curl -s -b /tmp/c3 $B/index.php | grep -o '<strong class="num">[0-9]*</strong><span>Data a combinar' | sed 's/<[^>]*>/ /g'
echo "separacao/rota nao quebram:"; curl -s -b /tmp/c2 -o /dev/null -w "%{http_code} " $B/separacao.php; curl -s -b /tmp/c3 -o /dev/null -w "%{http_code}\n" $B/rota.php
echo "busca:"; curl -s -b /tmp/c1 "$B/pedidos.php?busca=Prazo+Aberto" | grep -c 'Cliente Prazo Aberto'
echo "-- VENDEDORES (admin) e filtro:"
echo "balcao nao acessa (403):"; curl -s -b /tmp/c1 -o /dev/null -w "%{http_code}\n" $B/vendedores.php
echo "lista inicial (Ione, Bruno semeados):"; curl -s -b /tmp/ad $B/vendedores.php | grep -o '<strong>[A-Za-z]*</strong><span class="tag">[0-9]* pedido' | sed 's/<[^>]*>/ /g'
T=$(curl -s -b /tmp/ad $B/vendedores.php | tok)
curl -s -b /tmp/ad --data-urlencode "csrf=$T" --data-urlencode "acao=novo" --data-urlencode "nome=Carla" $B/vendedores.php | grep -o 'Vendedor Carla cadastrado'
curl -s -b /tmp/ad --data-urlencode "csrf=$T" --data-urlencode "acao=novo" --data-urlencode "nome=carla" $B/vendedores.php | grep -o 'Já existe um vendedor'
echo "novo pedido lista Carla no select:"; curl -s -b /tmp/c1 $B/pedido_novo.php | grep -o '<option value="Carla"' 
VID=$(php tests/sql.php -q "select id as m from vendedores where nome='Bruno'")
curl -s -b /tmp/ad --data-urlencode "csrf=$T" --data-urlencode "acao=editar" --data-urlencode "id=$VID" --data-urlencode "nome=Bruno Silva" $B/vendedores.php | grep -o 'Nome alterado de [^<]*'
php tests/sql.php -q "select 'pedidos com Bruno Silva: '||count(*) as s from pedidos where vendedor='Bruno Silva'"
echo "filtro por vendedor:"; curl -s -b /tmp/ad "$B/pedidos.php?vendedor=Bruno+Silva" | grep -o '[0-9]* pedidos\? de Bruno Silva' | head -1
echo "filtro por periodo (de/ate):"; curl -s -b /tmp/ad "$B/pedidos.php?de=$(date +%F)&ate=$(date -d '+1 day' +%F)" | grep -o '<span class="nota" style="margin:0">[^<]*' | sed 's/<[^>]*>//'
echo "balcao nao exclui vendedor (403):"; curl -s -b /tmp/c1 -o /dev/null -w "%{http_code}\n" -d "csrf=x&acao=excluir&id=$VID" $B/vendedores.php
curl -s -b /tmp/ad --data-urlencode "csrf=$T" --data-urlencode "acao=excluir" --data-urlencode "id=$VID" $B/vendedores.php | grep -o 'Vendedor Bruno Silva removido'
php tests/sql.php -q "select 'pedidos antigos mantem o nome: '||count(*) as s from pedidos where vendedor='Bruno Silva'"
