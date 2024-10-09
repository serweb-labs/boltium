<?= "<?php\n" ?>

namespace <?= $namespace; ?>;

use Symfony\Component\Security\Http\Attribute\IsGranted;

<?= $use_statements; ?>

class <?= $class_name; ?> extends AbstractController
{
<?= $route ?>
    public function <?= $method_name ?>(): <?php if ($with_template) { ?>Response<?php } else { ?>JsonResponse<?php } ?>

    {
<?php if ($with_template) { ?>
        return $this->render('<?= $template_name ?>', [
            'controller_name' => '<?= $class_name ?>',
        ]);
<?php } else { ?>
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => '<?= $relative_path; ?>',
        ]);
<?php } ?>
    }
}