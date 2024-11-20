<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigDatesType extends AbstractType
{
  public function buildForm(FormBuilderInterface $builder, array $options)
  {
    $builder
      ->add('date_debut', DateType::class, [
        'widget' => 'single_text',
        'label' => 'Date de début',
        'required' => true,
      ])
      ->add('date_fin', DateType::class, [
        'widget' => 'single_text',
        'label' => 'Date de fin',
        'required' => true,
      ]);
  }

  public function configureOptions(OptionsResolver $resolver)
  {
    $resolver->setDefaults([]);
  }
}
