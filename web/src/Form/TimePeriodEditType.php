<?php

namespace App\Form;

use App\Enum\TypePeriodEnum;
use App\Form\DataTransformer\EndDateTimeTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\TimePeriod;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class TimePeriodEditType extends AbstractType
{
  private EndDateTimeTransformer $endDateTransformer;

  public function __construct(EndDateTimeTransformer $endDateTransformer)
  {
    $this->endDateTransformer = $endDateTransformer;
  }
  public function buildForm(FormBuilderInterface $builder, array $options): void
  {
    $builder
      ->add('name', TextType::class, [
        'label' => 'Name',
      ])
      ->add('startDate', DateType::class, [
        'widget' => 'single_text',
        'label' => 'Start Date',
      ])
      ->add('endDate', DateType::class, [
        'widget' => 'single_text',
        'label' => 'End Date',
      ])
      ->add('type', EnumType::class, [
        'class' => TypePeriodEnum::class
      ])
      ->add('estimatedVelocity', NumberType::class, [
      'label' => 'Vélocité estimée',
      'required' => false,
      'scale' => 0, // Nombre de décimales, ajustez si nécessaire
      'disabled' => true
    ])
      // Champ pour la vélocité finale
      ->add('finalVelocity', NumberType::class, [
        'label' => 'Vélocité finale',
        'required' => false,
        'scale' => 0, // Nombre de décimales, ajustez si nécessaire
      ])
      // Champ pour la vélocité finale
      ->add('communicatedVelocity', NumberType::class, [
        'label' => 'Vélocité communiqué',
        'required' => false,
        'scale' => 0, // Nombre de décimales, ajustez si nécessaire
      ])
      // Champ pour le nombre de points ajoutés dans le sprint
      ->add('pointsAdded', IntegerType::class, [
        'label' => 'Nombre de points ajoutés',
        'required' => false,
      ]);
    $builder->get('endDate')->addModelTransformer($this->endDateTransformer);

  }

  public function configureOptions(OptionsResolver $resolver): void
  {
    $resolver->setDefaults([
      'data_class' => TimePeriod::class,
    ]);
  }
}
