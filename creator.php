<?php
ini_set('display_errors', 1);
ini_set('display_startup_erros', 1);
error_reporting(E_ALL);

class Creator {
    private $con;
    private $servidor;
    private $banco;
    private $usuario;
    private $senha;
    private $tabelas;

    function __construct() {
        $this->criaDiretorios();
        $this->conectar();
        $this->buscaTabelas();
        $this->ClassesModel();
        $this->ClasseConexao();
        $this->ClassesControl();
        $this->compactar();
        header("Location:index.php?msg=2");
    }

    function criaDiretorios() {
        $dirs = [
            "sistema",
            "sistema/model",
            "sistema/control",
            "sistema/view",
            "sistema/dao"
        ];

        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                if (!mkdir($dir, 0777, true)) {
                    header("Location:index.php?msg=0");
                }
            }
        }
    }

    function conectar() {
        $this->servidor = $_POST["servidor"];
        $this->banco = $_POST["banco"];
        $this->usuario = $_POST["usuario"];
        $this->senha = $_POST["senha"];

        try {
            $this->con = new PDO(
                "mysql:host=" . $this->servidor . ";dbname=" . $this->banco,
                $this->usuario,
                $this->senha
            );
        } catch (Exception $e) {
            header("Location:index.php?msg=1");
        }
    }

    function buscaTabelas() {
        $sql = "SHOW TABLES";
        $query = $this->con->query($sql);
        $this->tabelas = $query->fetchAll(PDO::FETCH_ASSOC);
    }

    function buscaAtributos($nomeTabela) {
        $sql = "SHOW COLUMNS FROM " . $nomeTabela;
        return $this->con->query($sql)->fetchAll(PDO::FETCH_OBJ);
    }

    function ClassesModel() {
        foreach ($this->tabelas as $tabela) {
            $nomeTabela = array_values((array) $tabela)[0];
            $atributos = $this->buscaAtributos($nomeTabela);
            $nomeAtributos = "";
            $geters_seters = "";

            foreach ($atributos as $atributo) {
                $atributo = $atributo->Field;
                $nomeAtributos .= "\tprivate \${$atributo};\n";
                $metodo = ucfirst($atributo);
                $geters_seters .= "\tfunction get{$metodo}() {\n";
                $geters_seters .= "\t\treturn \$this->{$atributo};\n\t}\n";
                $geters_seters .= "\tfunction set{$metodo}(\${$atributo}) {\n";
                $geters_seters .= "\t\t\$this->{$atributo} = \${$atributo};\n\t}\n";
            }

            $nomeClasse = ucfirst($nomeTabela);
            $conteudo = <<<EOT
<?php
class {$nomeClasse} {
{$nomeAtributos}
{$geters_seters}
}
?>
EOT;
            file_put_contents("sistema/model/{$nomeClasse}.php", $conteudo);
        }
    }

    function ClasseConexao() {
        $conteudo = <<<EOT
<?php
class Conexao {
    private \$server;
    private \$banco;
    private \$usuario;
    private \$senha;

    function __construct() {
        \$this->server = '[Informe aqui o servidor]';
        \$this->banco = '[Informe aqui o seu Banco de dados]';
        \$this->usuario = '[Informe aqui o usuário do banco de dados]';
        \$this->senha = '[Informe aqui a senha do banco de dados]';
    }

    function conectar() {
        try {
            \$conn = new PDO(
                "mysql:host=" . \$this->server . ";dbname=" . \$this->banco,
                \$this->usuario,
                \$this->senha
            );
            return \$conn;
        } catch (Exception \$e) {
            echo "Erro ao conectar com o Banco de dados: " . \$e->getMessage();
        }
    }
}
?>
EOT;
        file_put_contents("sistema/model/conexao.php", $conteudo);
    }

    function ClassesControl() {
        foreach ($this->tabelas as $tabela) {
            $nomeTabela = array_values((array) $tabela)[0];
            $nomeClasse = ucfirst($nomeTabela);

            $conteudo = <<<EOT
<?php
require_once("../model/{$nomeClasse}.php");
require_once("../dao/{$nomeClasse}Dao.php");

class {$nomeClasse}Control {
    private \${$nomeTabela};
    private \$acao;
    private \$dao;

    public function __construct() {
        \$this->{$nomeTabela} = new {$nomeClasse}();
        \$this->dao = new {$nomeClasse}Dao();
        \$this->acao = \$_GET["a"];
        \$this->verificaAcao();
    }

    function verificaAcao() {}
    function inserir() {}
    function excluir() {}
    function alterar() {}
    function buscarId({$nomeClasse} \${$nomeTabela}) {}
    function buscaTodos() {}
}

new {$nomeClasse}Control();
?>
EOT;
            file_put_contents("sistema/control/{$nomeTabela}Control.php", $conteudo);
        }
    }

    function compactar() {
        $folderToZip = 'sistema';
        $outputZip = 'sistema.zip';

        $zip = new ZipArchive();

        if ($zip->open($outputZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            return false;
        }

        $folderPath = realpath($folderToZip);
        if (!is_dir($folderPath)) {
            return false;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folderPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($folderPath) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
        return true;
    }

    function classesView() {
        foreach ($this->tabelas as $tabela) {
            $nomeTabela = array_values((array) $tabela)[0];
            $atributos = $this->buscaAtributos($nomeTabela);
            $formCampos = "";

            foreach ($atributos as $atributo) {
                $atributoNome = $atributo->Field;
                $formCampos .= "<label for='{$atributoNome}'>{$atributoNome}</label>\n";
                $formCampos .= "<input type='text' name='{$atributoNome}'><br>\n";
            }

            $conteudo = <<<HTML
<html>
    <head>
        <title>Cadastro de {$nomeTabela}</title>
    </head>
    <body>
        <form method="post" action="#">
            {$formCampos}
        </form>
    </body>
</html>
HTML;

            file_put_contents("sistema/view/{$nomeTabela}.php", $conteudo);
        }
    }
}

new Creator();
