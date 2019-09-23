<?php
/**
 * @var \App\View\AppView $this
 */
?>
<div class="container">
    <div class="row">
        <div class="col-xs-offset-1 col-xs-10 col-sm-offset-2 col-sm-8 col-md-6 col-md-offset-3">
            <div class="users form well well-lg">
            	<h1><?= __d('GoogleAuthenticatorAction', 'Verify'); ?>: <?= $_name; ?></h1>
                <?= $this->Form->create(null, ['url', $_url]) ?>
                <?= $this->Flash->render() ?>
                <fieldset>
                    <?= $this->Form->control('_verificationCode', ['required' => true, 'autofocus' => true, 'autocomplete' => "off", 'label' => __d('GoogleAuthenticatorAction', 'Verification Code')]) ?>
                </fieldset>
                <?= $this->Form->button(__d('GoogleAuthenticatorAction', 'Verify'), ['class' => 'btn btn-primary']); ?>
                <?= $this->Form->end() ?>
            </div>
        </div>
    </div>
</div>
