import React from 'react';

const ToolCall = ({ name, status }) => {
  const getIcon = () => {
    if (status === 'in_progress') {
      return <div className="spinner"></div>;
    }
    if (status === 'success') {
      return <div className="icon">✅</div>;
    }
    if (status === 'error') {
      return <div className="icon">❌</div>;
    }
    return null;
  };

  return (
    <div className="tool-call">
      {getIcon()}
      <span className="tool-call-name">{name}</span>
      {status !== 'in_progress' && (
        <span className="tool-call-status">{status}</span>
      )}
    </div>
  );
};

export default ToolCall; 