// src/js/editor/chatStreamApi.js

export async function streamChatMessage(messages, postId, onEvent, onDone, onError) {
  console.log('Wordsurf DEBUG: streamChatMessage called with messages:', messages);
  console.log('Wordsurf DEBUG: postId:', postId);
  try {
    const formData = new FormData();
    formData.append('action', 'wordsurf_stream_chat');
    formData.append('messages', JSON.stringify(messages));
    formData.append('post_id', postId);
    formData.append('nonce', window.wordsurfData?.nonce || '');

    const response = await fetch('/wp-admin/admin-ajax.php', {
      method: 'POST',
      body: formData,
    });

    if (!response.body) throw new Error('No response body');

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    while (true) {
      const { value, done } = await reader.read();
      if (done) break;
      
      buffer += decoder.decode(value, { stream: true });

      let events = buffer.split('\n\n');
      buffer = events.pop(); 

      for (const event of events) {
        if (event.startsWith('data: ')) {
          const dataLine = event.replace('data: ', '');
          try {
            const parsed = JSON.parse(dataLine);
            onEvent(parsed);
          } catch (e) {
            console.error('Wordsurf DEBUG: JSON parse error:', e.message, 'for event:', dataLine);
          }
        }
      }
    }
    if (onDone) onDone();
  } catch (err) {
    console.error('Wordsurf DEBUG: Stream error:', err);
    if (onError) onError(err);
  }
} 