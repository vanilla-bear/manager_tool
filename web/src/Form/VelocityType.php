<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VelocityType extends AbstractType
{
  public function buildForm(FormBuilderInterface $builder, array $options)
  {
    $builder
      ->add('velocity', NumberType::class, [
        'label' => 'Vélocité de l\'équipe',
        'required' => true,
        'scale' => 0, // Nombre de décimales, ajustez si nécessaire
        'attr' => [
          'min' => 0,
        ],
      ])
      ->add('explanation', TextareaType::class, [
        'label' => 'Explication du choix de cette vélocité',
        'required' => false,
        'help' => 'Documentez pourquoi vous avez choisi cette vélocité (ex: basée sur les 3 derniers sprints, ajustée pour les congés, etc.)',
        'attr' => [
          'rows' => 4,
          'placeholder' => 'Ex: Cette vélocité de 39 points/sprint est basée sur la moyenne des 3 derniers sprints où l\'équipe a livré 35, 42 et 40 points respectivement...'
        ],
      ]);
  }

  public function configureOptions(OptionsResolver $resolver)
  {
    $resolver->setDefaults([]);
  }
}
