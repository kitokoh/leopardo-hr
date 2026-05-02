// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'form_schema.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

FormSchema _$FormSchemaFromJson(Map<String, dynamic> json) => FormSchema(
      fields: (json['fields'] as List<dynamic>)
          .map((e) => FormField.fromJson(e as Map<String, dynamic>))
          .toList(),
      validation: json['validation'] as Map<String, dynamic>?,
      submitEndpoint: json['submit_endpoint'] as String?,
      submitMethod: json['submit_method'] as String?,
      labels: (json['labels'] as Map<String, dynamic>?)?.map(
        (k, e) => MapEntry(k, e as String),
      ),
    );

Map<String, dynamic> _$FormSchemaToJson(FormSchema instance) =>
    <String, dynamic>{
      'fields': instance.fields.map((e) => e.toJson()).toList(),
      if (instance.validation case final value?) 'validation': value,
      if (instance.submitEndpoint case final value?) 'submit_endpoint': value,
      if (instance.submitMethod case final value?) 'submit_method': value,
      if (instance.labels case final value?) 'labels': value,
    };

FormField _$FormFieldFromJson(Map<String, dynamic> json) => FormField(
      name: json['name'] as String,
      type: $enumDecode(_$FormFieldTypeEnumMap, json['type']),
      label: json['label'] as String,
      required: json['required'] as bool? ?? false,
      validation: json['validation'] as Map<String, dynamic>?,
      defaultValue: json['default_value'],
      options: (json['options'] as List<dynamic>?)
          ?.map((e) => FormFieldOption.fromJson(e as Map<String, dynamic>))
          .toList(),
      placeholder: json['placeholder'] as String?,
      helpText: json['help_text'] as String?,
    );

Map<String, dynamic> _$FormFieldToJson(FormField instance) => <String, dynamic>{
      'name': instance.name,
      'type': _$FormFieldTypeEnumMap[instance.type]!,
      'label': instance.label,
      'required': instance.required,
      if (instance.validation case final value?) 'validation': value,
      if (instance.defaultValue case final value?) 'default_value': value,
      if (instance.options?.map((e) => e.toJson()).toList() case final value?)
        'options': value,
      if (instance.placeholder case final value?) 'placeholder': value,
      if (instance.helpText case final value?) 'help_text': value,
    };

const _$FormFieldTypeEnumMap = {
  FormFieldType.text: 'text',
  FormFieldType.email: 'email',
  FormFieldType.password: 'password',
  FormFieldType.number: 'number',
  FormFieldType.phone: 'phone',
  FormFieldType.url: 'url',
  FormFieldType.textarea: 'textarea',
  FormFieldType.select: 'select',
  FormFieldType.multiselect: 'multiselect',
  FormFieldType.checkbox: 'checkbox',
  FormFieldType.radio: 'radio',
  FormFieldType.date: 'date',
  FormFieldType.datetime: 'datetime',
  FormFieldType.time: 'time',
  FormFieldType.file: 'file',
  FormFieldType.image: 'image',
  FormFieldType.hidden: 'hidden',
};

FormFieldOption _$FormFieldOptionFromJson(Map<String, dynamic> json) =>
    FormFieldOption(
      value: json['value'] as String,
      label: json['label'] as String,
      disabled: json['disabled'] as bool? ?? false,
    );

Map<String, dynamic> _$FormFieldOptionToJson(FormFieldOption instance) =>
    <String, dynamic>{
      'value': instance.value,
      'label': instance.label,
      'disabled': instance.disabled,
    };

FormValidationResult _$FormValidationResultFromJson(
        Map<String, dynamic> json) =>
    FormValidationResult(
      isValid: json['is_valid'] as bool,
      errors: Map<String, String>.from(json['errors'] as Map),
    );

Map<String, dynamic> _$FormValidationResultToJson(
        FormValidationResult instance) =>
    <String, dynamic>{
      'is_valid': instance.isValid,
      'errors': instance.errors,
    };
