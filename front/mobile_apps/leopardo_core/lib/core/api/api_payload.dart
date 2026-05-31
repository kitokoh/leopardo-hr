List<dynamic> extractDataList(dynamic payload) {
  if (payload is List) return payload;

  if (payload is Map) {
    final data = payload['data'];
    if (data is List) return data;
    if (data is Map && data['data'] is List) {
      return data['data'] as List;
    }

    final items = payload['items'];
    if (items is List) return items;
  }

  return const <dynamic>[];
}

Map<String, dynamic> extractDataMap(dynamic payload) {
  if (payload is Map) {
    final data = payload['data'];
    if (data is Map) return data.cast<String, dynamic>();
    return payload.cast<String, dynamic>();
  }

  return const <String, dynamic>{};
}
