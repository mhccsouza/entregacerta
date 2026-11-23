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
curl -s -b /tmp/c1 --data-urlencode "csrf=$T" --data-urlencode "nome=X" $B/pedido_novo.php | grep -o 'class="erro">[^<]*'
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
echo "-- painel:"; curl -s -b /tmp/c3 $B/index.php | grep -o '<strong style="font-size:1.8rem">[0-9]*</strong><br>[^<]*' | sed 's/<[^>]*>/ /g'
