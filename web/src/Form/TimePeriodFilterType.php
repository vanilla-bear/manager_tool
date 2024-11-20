<?php

// src/Form/TimePeriodFilterType.php
namespace App\Form;

use App\Enum\TypePeriodEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TimePeriodFilterType extends AbstractType
{
  public function buildForm(FormBuilderInterface $builder, array $options)
  {
    $builder
      ->add('name', TextType::class, [
        'required' => false,
        'label' => 'Name',
        'attr' => ['class' => 'form-control', 'placeholder' => 'Search by name']
      ])
      ->add('type', EnumType::class, [
        'class' => TypePeriodEnum::class
      ]);
  }

  public function configureOptions(OptionsResolver $resolver)
  {
    $resolver->setDefaults([]);
  }
}
