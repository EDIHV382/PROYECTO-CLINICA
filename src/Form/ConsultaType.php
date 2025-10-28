<?php

namespace App\Form;

use App\Entity\Consulta;
use App\Entity\Paciente;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConsultaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEditMode = $options['is_edit_mode'];

        $builder
            ->add('fechaConsulta', DateTimeType::class, [
                'label' => 'Fecha y Hora',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'disabled' => !$isEditMode, // Deshabilitado si NO estamos en modo edición
            ])
            ->add('motivo', TextareaType::class, [
                'label' => 'Motivo de la Consulta',
                'attr' => ['class' => 'form-control', 'rows' => 3],
            ])
            ->add('diagnostico', TextareaType::class, [
                'label' => 'Diagnóstico',
                'attr' => ['class' => 'form-control', 'rows' => 4],
            ])
            ->add('tratamiento', TextareaType::class, [
                'label' => 'Tratamiento',
                'attr' => ['class' => 'form-control', 'rows' => 4],
            ])
            ->add('observacion', TextareaType::class, [
                'label' => 'Observaciones (Opcional)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 2],
            ]);
        
        // El campo 'paciente' se ha eliminado del formulario.
        // Se asignará en el controlador para mayor seguridad.
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Consulta::class,
            'is_edit_mode' => false, // Por defecto, está en modo "crear"
        ]);
    }
}